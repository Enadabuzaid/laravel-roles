<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;

final class HighRiskConfirmRule implements Guardrail
{
    public function check(Operation $operation): void
    {
        if ($operation->confirmed || ! config('roles.guardrails.confirm_high_risk', true)) {
            return;
        }

        foreach ($operation->addedPermissions as $permission) {
            if ($permission->risk === Permission::RISK_HIGH) {
                throw Violation::highRiskConfirmRequired();
            }
        }

        foreach ($operation->added as $role) {
            if (RoleRisk::level($role) !== 'normal') {
                throw Violation::highRiskConfirmRequired();
            }
        }
    }
}
