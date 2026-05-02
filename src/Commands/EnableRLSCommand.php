<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Commands;

use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Migration\RLSHelper;

class EnableRLSCommand extends Command
{
    protected $signature = 'tenancy:rls:enable {table} {--column=tenant_id} {--policy=}';

    protected $description = 'Enable shared-DB Postgres RLS on a tenant-scoped table.';

    public function handle(): int
    {
        $table = (string) $this->argument('table');
        $column = (string) $this->option('column');
        $policy = $this->option('policy') ? (string) $this->option('policy') : null;

        RLSHelper::enable($table, $column, $policy);
        $this->info("RLS enabled on {$table} (column={$column}).");

        return self::SUCCESS;
    }
}
