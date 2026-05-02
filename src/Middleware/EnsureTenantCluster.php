<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Cluster\ClusterRegistry;
use Stancl\Tenancy\Tenancy;

/**
 * Enforces that the tenant's cluster matches the cluster the current app
 * server is configured for. On mismatch, redirects (308) to the right host
 * — preserving method + body so the client transparently follows.
 *
 * Set `clusters.redirect_disabled = true` to return 421 Misdirected Request
 * instead (recommended when running behind an upstream LB that should make
 * the routing decision).
 */
class EnsureTenantCluster
{
    public function __construct(
        protected Tenancy $tenancy,
        protected ClusterRegistry $clusters,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $this->tenancy->tenant;
        if (! $tenant) {
            return $next($request);
        }

        $tenantClusterId = (string) ($tenant->getAttribute('cluster_id') ?: $this->clusters->current()->id);
        $current = $this->clusters->current();

        if ($tenantClusterId === $current->id) {
            return $next($request);
        }

        $target = $this->clusters->get($tenantClusterId);
        if (! $target || ! $target->host || config('tenancy.clusters.redirect_disabled', false)) {
            return response()->json([
                'error' => 'misdirected_request',
                'message' => "Tenant lives on cluster '{$tenantClusterId}', this server is '{$current->id}'.",
                'cluster' => $tenantClusterId,
            ], 421);
        }

        return redirect()->away(
            rtrim($target->host, '/').$request->getRequestUri(),
            308,
        );
    }
}
