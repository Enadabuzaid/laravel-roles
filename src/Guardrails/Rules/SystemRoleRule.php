<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;

final class SystemRoleRule implements Guardrail
{
    public function check(Operation $operation): void
    {
        $role = $operation->role;
        if ($role === null) {
            return;
        }

        $blocked = match ($operation->type) {
            OperationType::DeleteRole, OperationType::ForceDeleteRole => true,
            OperationType::RenameRole => $operation->newName !== $role->name,
            default => false,
        };

        if ($blocked && ($role->is_system || RoleRisk::isOwnerRole($role))) {
            throw Violation::systemRole();
        }
    }
}
