<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;

/**
 * The super role always has full access: its permissions can't be edited by anyone, the system included.
 * There is deliberately no toggle.
 */
final class OwnerRoleLockedRule implements Guardrail
{
    public function check(Operation $operation): void
    {
        if ($operation->type === OperationType::UpdateRolePermissions && $operation->role !== null && RoleRisk::isOwnerRole($operation->role)) {
            throw Violation::ownerRoleLocked();
        }
    }
}
