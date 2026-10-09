<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Models\Role;

final class NoEmptyRolesCheck implements Check
{
    public function name(): string
    {
        return 'no-empty-roles';
    }

    public function run(): CheckResult
    {
        $empty = Role::query()
            ->where('name', '!=', (string) config('roles.super_role'))
            ->whereDoesntHave('permissions')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $empty === []
            ? CheckResult::pass('Every role has permissions.')
            : CheckResult::warn('Roles with no permissions: '.implode(', ', $empty));
    }
}
