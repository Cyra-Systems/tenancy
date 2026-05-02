<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Cluster;

/**
 * Immutable description of a cluster (a horizontal-scale slice of the app).
 */
final class Cluster
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $host = null,
        public readonly string $redisPrefix = '',
        public readonly string $queuePrefix = '',
        public readonly string $cachePrefix = '',
    ) {
    }

    public static function fromArray(string $id, array $data): self
    {
        return new self(
            id: $id,
            name: $data['name'] ?? $id,
            host: $data['host'] ?? null,
            redisPrefix: $data['redis_prefix'] ?? $id,
            queuePrefix: $data['queue_prefix'] ?? $id,
            cachePrefix: $data['cache_prefix'] ?? $id,
        );
    }

    public function prefixFor(string $tenantId): string
    {
        return sprintf('%s:tenant:%s:', $this->id, $tenantId);
    }
}
