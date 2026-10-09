<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Models\Role;

final class OwnerExistsCheck implements Check
{
    public function name(): string
    {
        return 'owner-exists';
    }

    public function run(): CheckResult
    {
        $super = (string) config('roles.super_role');
        $count = Role::query()
            ->where('name', $super)
            ->where('guard_name', (string) config('roles.guard'))
            ->first()?->users()->count() ?? 0;

        return $count > 0
            ? CheckResult::pass("{$count} user(s) hold the [{$super}] role.")
            : CheckResult::fail("No user holds the [{$super}] role. Run roles:seed then roles:owner {email}.");
    }
}
