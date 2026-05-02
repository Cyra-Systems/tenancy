# Multitenant SaaS Boilerplate Note

This branch (`claude/multitenant-saas-boilerplate-L68a2`) is intentionally a
**no-op marker** on top of upstream `stancl/tenancy` master.

The headless multitenant SaaS boilerplate originally lifted helpers into this
fork (cluster registry, headless tenant resolver, RLS commands, reseller
concerns, etc). All of those primitives now live inside the consuming
application instead, under `app/Tenancy/*` of `cyra-systems/paymenter` on the
matching `claude/multitenant-saas-boilerplate-L68a2` branch:

| Concept                 | Now lives at (paymenter)                                          |
|-------------------------|-------------------------------------------------------------------|
| Headless tenant resolver| `app/Tenancy/Resolvers/HeadlessTenantResolver.php`                |
| Cluster value object    | `app/Tenancy/Cluster/Cluster.php`                                 |
| Cluster registry        | `app/Tenancy/Cluster/ClusterRegistry.php`                         |
| Cluster bootstrapper    | `app/Tenancy/Cluster/ClusterPrefixBootstrapper.php`               |
| RLS migration helper    | `app/Tenancy/Database/RLSHelper.php`                              |
| RLS artisan commands    | `app/Console/Commands/{EnableRLSCommand,CreateRLSUserCommand}.php`|
| Headless middleware     | `app/Http/Middleware/Api/{IdentifyTenantHeadless,EnsureCluster,ApiKeyAuth,ApiKeyOrPassport,SuperAdminRlsBypass,AppendClusterHeader}.php` |
| Reseller / cluster traits| `app/Models/Concerns/{HasReseller,HasCluster}.php`               |
| Tenant-scoped writes    | `app/Models/Concerns/BelongsToTenant.php`                         |
| Boilerplate config      | `config/multitenancy.php`                                         |

This fork should continue to track upstream `stancl/tenancy` so we can pick up
new bootstrappers, identification strategies, and RLS improvements without
maintaining a parallel patchset.
