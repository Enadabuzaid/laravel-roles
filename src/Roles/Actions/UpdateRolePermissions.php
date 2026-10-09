<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Support\RolePermissionPlanner;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Spatie\Permission\PermissionRegistrar;

final class UpdateRolePermissions extends Action
{
    /**
     * @param  list<int|string>  $grant  permission ids or names to grant
     * @param  list<int|string>  $revoke  permission ids or names to revoke
     * @return array{added: list<string>, removed: list<string>}
     */
    public function handle(Role $role, array $grant, array $revoke, bool $confirmed = false): array
    {
        app(ActorContext::class)->assertKnownActor();

        $result = $this->transaction(function () use ($role, $grant, $revoke, $confirmed): array {
            app(OwnerCounter::class)->lockSuperRole();
            $fresh = Role::query()->where('guard_name', (string) config('roles.guard'))->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            $planner = app(RolePermissionPlanner::class);
            $roleId = (int) $fresh->getKey();
            $plan = $planner->plan(
                $fresh,
                $grant,
                $revoke,
                $planner->resolve([...$grant, ...$revoke]),
                $planner->heldIds([$roleId])[$roleId],
            );

            $planner->assert($plan, $confirmed);

            $added = array_map(static fn (Permission $permission): string => $permission->name, $plan->added);
            $removed = array_map(static fn (Permission $permission): string => $permission->name, $plan->removed);

            if ($plan->isEmpty()) {
                return ['added' => [], 'removed' => []];
            }

            $fresh->unsetRelation('permissions');
            $before = $fresh->permissions()->orderBy('name')->pluck('name')->all();

            if ($plan->added !== []) {
                $fresh->givePermissionTo(...$plan->added);
            }
            if ($plan->removed !== []) {
                $fresh->revokePermissionTo($plan->removed);
            }

            $fresh->unsetRelation('permissions');
            $after = $fresh->permissions()->orderBy('name')->pluck('name')->all();

            app(AuditRecorder::class)->record(
                'roles',
                'permissions_updated',
                $fresh,
                ['permissions' => $before],
                ['permissions' => $after],
                ['added' => $added, 'removed' => $removed],
                $plan->addsHighRisk() ? Severity::Warning : Severity::Info,
            );

            return ['added' => $added, 'removed' => $removed];
        });

        $role->unsetRelation('permissions');

        if ($result['added'] !== [] || $result['removed'] !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $result;
    }
}
