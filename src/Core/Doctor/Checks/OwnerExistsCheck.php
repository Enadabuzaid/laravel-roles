<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Users\Support\SignInGate;

/** Passes only with at least one ACTIVE owner (suspended, deactivated and not-yet-active owners don't count). */
final class OwnerExistsCheck implements Check
{
    public function name(): string
    {
        return 'owner-exists';
    }

    public function run(): CheckResult
    {
        $super = (string) config('roles.super_role');
        $holders = Role::query()
            ->where('name', $super)
            ->where('guard_name', (string) config('roles.guard'))
            ->first()?->users()->count() ?? 0;

        $active = app(SignInGate::class)->enabled() ? app(OwnerCounter::class)->count() : $holders;

        if ($active > 0) {
            return CheckResult::pass("{$active} active user(s) hold the [{$super}] role.");
        }

        return $holders > 0
            ? CheckResult::fail("{$holders} user(s) hold the [{$super}] role but none is active (suspended or deactivated). Run roles:owner {email} to reactivate one.")
            : CheckResult::fail("No user holds the [{$super}] role. Run roles:seed then roles:owner {email}.");
    }
}
