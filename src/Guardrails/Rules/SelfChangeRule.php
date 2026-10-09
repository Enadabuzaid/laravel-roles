<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Illuminate\Support\Facades\DB;

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

        if ($operation->type === OperationType::RestoreRole && $this->actorHoldsRoleRow($operation)) {
            throw Violation::selfRoleChange();
        }
    }

    /**
     * Trashing a role keeps its member rows, so restoring it hands every member their access back;
     * the pivot is read directly because the roles() relation hides trashed roles.
     */
    private function actorHoldsRoleRow(Operation $operation): bool
    {
        $actor = $operation->actor;
        $role = $operation->role;

        if ($actor === null || $role === null || $this->owners->isOwner($actor)) {
            return false;
        }

        return DB::table((string) config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->getKey())
            ->where('model_type', $actor->getMorphClass())
            ->where((string) config('permission.column_names.model_morph_key', 'model_id'), $actor->getKey())
            ->exists();
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
