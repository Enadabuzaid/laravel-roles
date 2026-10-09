<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Support;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;

/**
 * The effective difference a permission change makes to one role (no-op entries already dropped).
 */
final class RolePermissionPlan
{
    /**
     * @param  list<Permission>  $added
     * @param  list<Permission>  $removed
     */
    public function __construct(
        public readonly Role $role,
        public readonly array $added,
        public readonly array $removed,
    ) {}

    public function isEmpty(): bool
    {
        return $this->added === [] && $this->removed === [];
    }

    public function addsHighRisk(): bool
    {
        foreach ($this->added as $permission) {
            if ($permission->risk === Permission::RISK_HIGH) {
                return true;
            }
        }

        return false;
    }
}
