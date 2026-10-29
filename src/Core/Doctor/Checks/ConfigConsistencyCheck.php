<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Illuminate\Support\Facades\Schema;

final class ConfigConsistencyCheck implements Check
{
    public function name(): string
    {
        return 'config-consistency';
    }

    public function run(): CheckResult
    {
        $problems = [];

        if ((bool) config('roles.teams') !== (bool) config('permission.teams')) {
            $problems[] = 'roles.teams and permission.teams must both be on or both be off (run roles:install --teams=1)';
        }

        if (config('permission.teams')) {
            $key = (string) config('permission.column_names.team_foreign_key', 'team_id');
            $table = (string) config('permission.table_names.roles', 'roles');

            if (Schema::hasTable($table) && ! Schema::hasColumn($table, $key)) {
                $problems[] = "teams are on but [{$table}.{$key}] is missing (publish and run the Spatie teams migration)";
            }
        }

        if (config('roles.id_type') !== 'int') {
            $problems[] = 'roles.id_type must be [int] in 2.0-alpha (uuid and ulid are planned for a later phase)';
        }

        return $problems === []
            ? CheckResult::pass('Teams settings are consistent and the key type is int.')
            : CheckResult::fail(implode('; ', $problems).'.');
    }
}
