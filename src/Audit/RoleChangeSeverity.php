<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;

final class RoleChangeSeverity
{
    /**
     * @param  list<Role>  $added
     * @param  list<Role>  $removed
     */
    public static function for(array $added, array $removed): Severity
    {
        foreach ([...$added, ...$removed] as $role) {
            if (RoleRisk::isOwnerRole($role)) {
                return Severity::Critical;
            }
        }

        foreach ($added as $role) {
            if (RoleRisk::level($role) === 'high') {
                return Severity::Warning;
            }
        }

        return Severity::Info;
    }
}
