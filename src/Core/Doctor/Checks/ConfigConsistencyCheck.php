<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;

final class ConfigConsistencyCheck implements Check
{
    public function name(): string
    {
        return 'config-consistency';
    }

    public function run(): CheckResult
    {
        $problems = [];

        if (config('roles.teams')) {
            $problems[] = 'roles.teams is enabled but teams are not supported in 2.0-alpha';
        }

        if (config('permission.teams')) {
            $problems[] = 'permission.teams is enabled but teams are not supported in 2.0-alpha';
        }

        if (config('roles.id_type') !== 'int') {
            $problems[] = 'roles.id_type must be [int] in 2.0-alpha (uuid and ulid are planned for a later phase)';
        }

        return $problems === []
            ? CheckResult::pass('Teams are off and the key type is int.')
            : CheckResult::fail(implode('; ', $problems).'.');
    }
}
