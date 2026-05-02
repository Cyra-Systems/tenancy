<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Cluster;

use RuntimeException;

/**
 * Loaded once per request from config('tenancy.clusters'). Holds the current
 * cluster id and the map of all known clusters.
 */
class ClusterRegistry
{
    /** @var array<string, Cluster> */
    protected array $clusters = [];

    protected string $currentId = 'default';

    public function __construct(array $config = [])
    {
        $this->currentId = (string) ($config['current'] ?? 'default');

        foreach ((array) ($config['map'] ?? []) as $id => $data) {
            $this->clusters[(string) $id] = Cluster::fromArray((string) $id, (array) $data);
        }

        if (! isset($this->clusters[$this->currentId])) {
            $this->clusters[$this->currentId] = new Cluster(
                id: $this->currentId,
                name: $this->currentId,
            );
        }
    }

    public function current(): Cluster
    {
        return $this->clusters[$this->currentId]
            ?? throw new RuntimeException("Current cluster '{$this->currentId}' is not registered.");
    }

    public function get(string $id): ?Cluster
    {
        return $this->clusters[$id] ?? null;
    }

    /** @return array<string, Cluster> */
    public function all(): array
    {
        return $this->clusters;
    }

    public function has(string $id): bool
    {
        return isset($this->clusters[$id]);
    }
}
