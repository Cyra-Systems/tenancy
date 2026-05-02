<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Database\Migration;

use Illuminate\Support\Facades\DB;

/**
 * Migration helper for shared-DB Postgres Row Level Security.
 *
 * Usage from a migration:
 *
 *   use Stancl\Tenancy\Database\Migration\RLSHelper;
 *
 *   Schema::create('products', function (Blueprint $t) { ... });
 *   RLSHelper::enable('products');
 *
 * Emitted SQL:
 *   ALTER TABLE products ENABLE ROW LEVEL SECURITY;
 *   ALTER TABLE products FORCE ROW LEVEL SECURITY;
 *   CREATE POLICY products_rls_policy ON products
 *     USING ((tenant_id::text = current_setting('app.current_tenant', true))
 *         OR (current_setting('app.current_tenant', true) = '*'))
 *     WITH CHECK (tenant_id::text = current_setting('app.current_tenant', true));
 */
final class RLSHelper
{
    public static function enable(string $table, string $column = 'tenant_id', ?string $policyName = null): void
    {
        $sessionVar = (string) config('tenancy.rls.session_variable_name', 'app.current_tenant');
        $sentinel = (string) config('tenancy.rls.super_admin_sentinel', '*');
        $policy = $policyName ?: $table.'_rls_policy';

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement(
            "CREATE POLICY {$policy} ON {$table} ".
            "USING ((${column}::text = current_setting('{$sessionVar}', true)) ".
            "OR (current_setting('{$sessionVar}', true) = '{$sentinel}')) ".
            "WITH CHECK ({$column}::text = current_setting('{$sessionVar}', true))"
        );
    }

    public static function disable(string $table, ?string $policyName = null): void
    {
        $policy = $policyName ?: $table.'_rls_policy';
        DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }
}
