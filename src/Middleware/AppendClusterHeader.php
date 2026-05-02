<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Cluster\ClusterRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds `X-Cluster: <current>` to every response so upstream load balancers
 * can pin sessions and operators can sanity-check routing in dev tools.
 */
class AppendClusterHeader
{
    public function __construct(protected ClusterRegistry $clusters)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Cluster', $this->clusters->current()->id);

        return $response;
    }
}
