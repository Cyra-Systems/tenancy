<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * White-label reseller hierarchy for the tenant model.
 *
 * Schema additions expected on the tenants table:
 *   - parent_tenant_id (ulid, nullable, FK -> tenants.id)
 *   - is_reseller (boolean, default false)
 */
trait HasReseller
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_tenant_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_tenant_id');
    }

    public function isReseller(): bool
    {
        return (bool) $this->getAttribute('is_reseller');
    }

    public function belongsToReseller(): bool
    {
        return $this->getAttribute('parent_tenant_id') !== null;
    }

    public function rootReseller(): ?self
    {
        $node = $this;
        while ($node->parent_tenant_id) {
            $node = $node->parent;
            if (! $node) {
                break;
            }
        }

        return $node?->isReseller() ? $node : null;
    }
}
