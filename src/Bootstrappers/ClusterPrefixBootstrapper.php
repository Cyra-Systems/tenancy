<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository;
use Stancl\Tenancy\Cluster\ClusterRegistry;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Per-cluster Redis / queue / cache prefixing.
 *
 * Runs after PostgresRLSBootstrapper so DB context is already correct. The
 * effect: a queue worker on the eu-1 box sees only `eu-1:tenant:<id>:*`
 * jobs and a cache hit on us-1 cannot leak into eu-1.
 */
class ClusterPrefixBootstrapper implements TenancyBootstrapper
{
    /** @var array<string, mixed> */
    protected array $originals = [];

    public function __construct(
        protected Repository $config,
        protected ClusterRegistry $clusters,
    ) {
    }

    public function bootstrap(Tenant $tenant): void
    {
        $clusterId = (string) ($tenant->getAttribute('cluster_id') ?: $this->clusters->current()->id);
        $cluster = $this->clusters->get($clusterId) ?? $this->clusters->current();
        $prefix = $cluster->prefixFor((string) $tenant->getTenantKey());

        $this->originals = [
            'cache.prefix' => $this->config->get('cache.prefix'),
            'database.redis.options.prefix' => $this->config->get('database.redis.options.prefix'),
            'queue.connections.redis.queue' => $this->config->get('queue.connections.redis.queue'),
        ];

        $this->config->set('cache.prefix', $prefix);
        $this->config->set('database.redis.options.prefix', $prefix);

        $existingQueue = (string) $this->config->get('queue.connections.redis.queue', 'default');
        $this->config->set('queue.connections.redis.queue', $cluster->queuePrefix.':'.$existingQueue);
    }

    public function revert(): void
    {
        foreach ($this->originals as $key => $value) {
            $this->config->set($key, $value);
        }
        $this->originals = [];
    }
}
