<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Guardrails\Violation;

final class SelfChangeRule implements Guardrail
{
    public function __construct(private readonly OwnerCounter $owners) {}

    public function check(Operation $operation): void
    {
        if (! config('roles.guardrails.prevent_self_escalation', true)) {
            return;
        }

        if ($operation->type->isRoleChange() && $operation->actorIsTarget()) {
            throw Violation::selfRoleChange();
        }

        if ($operation->type === OperationType::UpdateRolePermissions && $this->actorHoldsEditedRole($operation)) {
            throw Violation::selfRoleChange();
        }
    }

    /**
     * Editing a role you hold would widen your own access; owners are exempt.
     */
    private function actorHoldsEditedRole(Operation $operation): bool
    {
        $actor = $operation->actor;
        $role = $operation->role;

        if ($actor === null || $role === null || $this->owners->isOwner($actor)) {
            return false;
        }

        return $actor->roles()->whereKey($role->getKey())->exists(); // @phpstan-ignore method.notFound
    }
}
