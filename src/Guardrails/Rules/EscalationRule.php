<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;
use Illuminate\Database\Eloquent\Model;

final class EscalationRule implements Guardrail
{
    public function __construct(private readonly OwnerCounter $owners) {}

    public function check(Operation $operation): void
    {
        $actor = $operation->actor;
        if ($actor === null || ! config('roles.guardrails.prevent_escalation', true)) {
            return;
        }

        if ($operation->type === OperationType::UpdateRolePermissions) {
            $this->checkPermissionGrants($actor, $operation);

            return;
        }

        if (($operation->added === [] && $operation->removed === []) || $this->owners->isOwner($actor)) {
            return;
        }

        foreach ($operation->removed as $role) {
            if (RoleRisk::isOwnerRole($role)) {
                throw Violation::escalation();
            }
        }

        if ($operation->added === []) {
            return;
        }

        /** @var array<string, true> $held */
        $held = array_fill_keys(
            $actor->getAllPermissions()->pluck('name')->all(), // @phpstan-ignore method.notFound
            true,
        );

        foreach ($operation->added as $role) {
            $role->loadMissing('permissions');
            $missing = $role->permissions->pluck('name')->contains(fn (string $name): bool => ! isset($held[$name]));

            if (RoleRisk::isOwnerRole($role) || $missing) {
                throw Violation::escalation();
            }
        }
    }

    /**
     * A non-owner may only add permissions they hold themselves; removing is always allowed.
     */
    private function checkPermissionGrants(Model $actor, Operation $operation): void
    {
        if ($operation->addedPermissions === [] || $this->owners->isOwner($actor)) {
            return;
        }

        /** @var array<string, true> $held */
        $held = array_fill_keys(
            $actor->getAllPermissions()->pluck('name')->all(), // @phpstan-ignore method.notFound
            true,
        );

        foreach ($operation->addedPermissions as $permission) {
            if (! isset($held[$permission->name])) {
                throw Violation::escalation();
            }
        }
    }
}
