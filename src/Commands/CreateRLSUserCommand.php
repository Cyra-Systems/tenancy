<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates the dedicated Postgres role used by PostgresRLSBootstrapper.
 *
 * The role is FORCED through RLS — it cannot bypass policies even if it
 * owns the schema. Migrations and Filament admin should run under the
 * normal central role, not this one.
 */
class CreateRLSUserCommand extends Command
{
    protected $signature = 'tenancy:rls:user {--user=} {--password=}';

    protected $description = 'Create the Postgres role used for RLS-bound tenant connections.';

    public function handle(): int
    {
        $user = (string) ($this->option('user') ?: env('TENANCY_RLS_USER', 'tenant_app'));
        $password = (string) ($this->option('password') ?: env('TENANCY_RLS_PASSWORD', ''));

        if ($password === '') {
            $this->error('Password required (pass --password=... or set TENANCY_RLS_PASSWORD).');
            return self::FAILURE;
        }

        $database = (string) DB::connection()->getDatabaseName();

        DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '{$user}') THEN CREATE ROLE {$user} LOGIN PASSWORD '{$password}'; END IF; END $$");
        DB::statement("GRANT CONNECT ON DATABASE \"{$database}\" TO {$user}");
        DB::statement("GRANT USAGE ON SCHEMA public TO {$user}");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$user}");
        DB::statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$user}");
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$user}");
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO {$user}");

        $this->info("Role '{$user}' created/ensured with RLS-respecting grants on database '{$database}'.");

        return self::SUCCESS;
    }
}
