<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Resolvers\HeadlessTenantResolver;
use Stancl\Tenancy\Tenancy;

/**
 * Initializes tenancy from a headless API request.
 *
 * Drop into route groups exposed to clients (mobile apps, SPAs, third-party
 * SDKs). Returns 401 JSON when a tenant cannot be identified — apps that
 * need a custom error shape should subclass and override `onFail`.
 */
class InitializeTenancyHeadless
{
    public function __construct(
        protected Tenancy $tenancy,
        protected HeadlessTenantResolver $resolver,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $tenant = $this->resolver->resolve($request);
        } catch (TenantCouldNotBeIdentifiedException $e) {
            return $this->onFail($request, $e);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }

    protected function onFail(Request $request, TenantCouldNotBeIdentifiedException $e): mixed
    {
        return response()->json([
            'error' => 'tenant_not_identified',
            'message' => $e->getMessage(),
        ], 401);
    }
}
