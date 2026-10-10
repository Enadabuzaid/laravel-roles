<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;

/** The invitations module creates users with state rows, so it needs the users module; its routes are not registered without it. */
final class InvitationsRequireUsersCheck implements Check
{
    public function name(): string
    {
        return 'invitations-require-users';
    }

    public function run(): CheckResult
    {
        return config('roles.modules.invitations') && ! config('roles.modules.users')
            ? CheckResult::fail('roles.modules.invitations is on but roles.modules.users is off: invitation routes are not registered. Enable the users module or disable invitations.')
            : CheckResult::pass('Invitations and users modules are consistent.');
    }
}
