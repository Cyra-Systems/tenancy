<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Resolvers;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;

/**
 * Resolves a tenant for headless API requests.
 *
 * Order of resolution (first match wins):
 *   1. Header (default `X-Tenant`) — slug or ULID.
 *   2. API key — Authorization: Bearer <key>; resolved via a configured callback.
 *   3. JWT — `tenant` claim on a Passport access token.
 *
 * Concrete configuration lives in `config/tenancy.php` under
 * `identification.headless`. Hosts can override the api_key_lookup and
 * jwt_decoder callbacks to plug in their own auth stack.
 */
class HeadlessTenantResolver extends RequestDataTenantResolver
{
    public function resolveWithoutCache(...$args): Tenant
    {
        /** @var Request $request */
        $request = $args[0] ?? request();

        if ($tenant = $this->fromHeader($request)) {
            return $tenant;
        }

        if ($tenant = $this->fromApiKey($request)) {
            return $tenant;
        }

        if ($tenant = $this->fromJwt($request)) {
            return $tenant;
        }

        throw new TenantCouldNotBeIdentifiedException(
            'No tenant could be identified from header, API key, or JWT.'
        );
    }

    protected function fromHeader(Request $request): ?Tenant
    {
        $header = (string) config('tenancy.identification.headless.header', 'X-Tenant');
        $value = $request->header($header);

        if (! $value) {
            return null;
        }

        return $this->lookupTenant($value);
    }

    protected function fromApiKey(Request $request): ?Tenant
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }

        $lookup = config('tenancy.identification.headless.api_key_lookup');
        if (! $lookup instanceof Closure && ! is_callable($lookup)) {
            return null;
        }

        $tenantKey = $lookup($bearer, $request);

        return $tenantKey ? $this->lookupTenant($tenantKey) : null;
    }

    protected function fromJwt(Request $request): ?Tenant
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }

        $decoder = config('tenancy.identification.headless.jwt_decoder');
        if (! $decoder instanceof Closure && ! is_callable($decoder)) {
            return null;
        }

        $claim = (string) config('tenancy.identification.headless.jwt_claim', 'tenant');
        $payload = $decoder($bearer, $request);

        if (! is_array($payload) || ! isset($payload[$claim])) {
            return null;
        }

        return $this->lookupTenant((string) $payload[$claim]);
    }

    protected function lookupTenant(string $key): ?Tenant
    {
        /** @var class-string<Model&Tenant> $model */
        $model = config('tenancy.tenant_model');

        return $model::query()
            ->where('id', $key)
            ->orWhere('slug', $key)
            ->first();
    }
}
