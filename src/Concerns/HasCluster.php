<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Concerns;

use Stancl\Tenancy\Cluster\Cluster;
use Stancl\Tenancy\Cluster\ClusterRegistry;

/**
 * Each tenant lives on exactly one cluster. The cluster id selects which
 * Redis prefix, queue connection, and host serve the tenant — see
 * config/tenancy.php `clusters` for the registry.
 */
trait HasCluster
{
    public function clusterId(): string
    {
        return (string) ($this->getAttribute('cluster_id') ?: config('tenancy.clusters.current', 'default'));
    }

    public function cluster(): ?Cluster
    {
        return app(ClusterRegistry::class)->get($this->clusterId());
    }

    public function belongsToCluster(string $id): bool
    {
        return $this->clusterId() === $id;
    }

    public function isOnCurrentCluster(): bool
    {
        return $this->clusterId() === (string) config('tenancy.clusters.current', 'default');
    }
}
