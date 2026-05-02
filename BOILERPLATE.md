# Cyra Headless Multi-Tenant SaaS — `tenancy` package additions

This branch adds the building blocks the Cyra boilerplate needs on top of `stancl/tenancy`. Everything lives under `Stancl\Tenancy\*`; no upstream classes are renamed or removed.

## What's in here

| Concern | Class |
| --- | --- |
| Headless tenant identification | `Stancl\Tenancy\Resolvers\HeadlessTenantResolver`, `Stancl\Tenancy\Middleware\InitializeTenancyHeadless` |
| Reseller hierarchy (white-label) | `Stancl\Tenancy\Concerns\HasReseller` |
| Cluster awareness | `Stancl\Tenancy\Concerns\HasCluster`, `Stancl\Tenancy\Cluster\Cluster`, `Stancl\Tenancy\Cluster\ClusterRegistry`, `Stancl\Tenancy\Bootstrappers\ClusterPrefixBootstrapper`, `Stancl\Tenancy\Middleware\EnsureTenantCluster`, `Stancl\Tenancy\Middleware\AppendClusterHeader` |
| Postgres RLS (shared DB) | `Stancl\Tenancy\Database\Migration\RLSHelper`, `Stancl\Tenancy\Commands\EnableRLSCommand`, `Stancl\Tenancy\Commands\CreateRLSUserCommand` |

The upstream `Stancl\Tenancy\Bootstrappers\PostgresRLSBootstrapper` does the actual `SET app.current_tenant = '<ulid>'` on each request.

## Headless tenant identification

`HeadlessTenantResolver` consults three sources in order, first match wins:

1. `X-Tenant` header — slug or ULID.
2. API key — `Authorization: Bearer <key>` resolved via the configured `api_key_lookup` callback to a tenant.
3. JWT — the `tenant` claim from a Passport access token.

The header name and JWT claim name are configurable; see `config/tenancy.php` `identification.headless`.

Register the middleware on routes that need a tenant context:

```php
Route::middleware(['tenant.headless', 'tenant.cluster'])->group(function () {
    Route::get('/v1/products', [CatalogController::class, 'index']);
});
```

## Reseller hierarchy

Use `HasReseller` on your `Tenant` model. Schema:

```php
$table->ulid('parent_tenant_id')->nullable()->index();
$table->boolean('is_reseller')->default(false);
$table->foreign('parent_tenant_id')->references('id')->on('tenants')->nullOnDelete();
```

API:

- `$tenant->parent()` — parent tenant if this is a child.
- `$tenant->children()` — direct children if this tenant is a reseller.
- `$tenant->isReseller()`, `$tenant->belongsToReseller()`.

## Cluster awareness

Every tenant has a `cluster_id`. Configure your clusters in `config/tenancy.php`:

```php
'clusters' => [
    'current' => env('TENANCY_CLUSTER_ID', 'default'),
    'map' => [
        'eu-1' => ['host' => 'https://eu-1.example.com', 'redis_prefix' => 'eu1', 'queue_prefix' => 'eu1', 'cache_prefix' => 'eu1'],
        'us-1' => ['host' => 'https://us-1.example.com', 'redis_prefix' => 'us1', 'queue_prefix' => 'us1', 'cache_prefix' => 'us1'],
    ],
],
```

When `EnsureTenantCluster` runs and the tenant's `cluster_id` ≠ `clusters.current`, the response is `308 Permanent Redirect` to the right cluster's `host` (preserves method + body). Set `clusters.redirect_disabled = true` to return `421 Misdirected Request` instead.

`ClusterPrefixBootstrapper` mutates `cache.prefix`, `database.redis.options.prefix`, and `queue.connections.*.queue` so workers only handle their own cluster's jobs.

`AppendClusterHeader` is a terminable middleware that adds `X-Cluster: <id>` to every response — use it to drive upstream load-balancer session pinning.

## RLS recipe

One-time DB setup:

```bash
php artisan tenancy:rls:user                   # creates the tenant_app role
```

In each migration that creates a tenant-scoped table:

```php
use Stancl\Tenancy\Database\Migration\RLSHelper;

Schema::create('products', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->ulid('tenant_id')->index();
    // ...
});

RLSHelper::enable('products');
```

`RLSHelper::enable` emits:

```sql
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE products FORCE ROW LEVEL SECURITY;
CREATE POLICY products_rls_policy ON products
  USING ((tenant_id::text = current_setting('app.current_tenant', true))
      OR (current_setting('app.current_tenant', true) = '*'))
  WITH CHECK (tenant_id::text = current_setting('app.current_tenant', true));
```

The `'*'` sentinel is the super-admin bypass: when an authenticated super-admin request sets `SET app.current_tenant = '*'`, every policy returns true. The `WITH CHECK` clause still prevents cross-tenant inserts.

## Required env

```
TENANCY_HEADER=X-Tenant
TENANCY_JWT_CLAIM=tenant
TENANCY_RLS_USER=tenant_app
TENANCY_RLS_PASSWORD=secret
TENANCY_CLUSTER_ID=eu-1
```
