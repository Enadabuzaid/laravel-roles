<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Support\RolePermissionPlanner;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\PermissionRegistrar;

final class UpdateMatrix extends Action
{
    /**
     * All roles are planned and every guardrail asserted before the first write; the writes then
     * run in the same transaction, so a violation anywhere changes nothing and audits nothing.
     *
     * @param  array<int, array{grant?: list<int|string>, revoke?: list<int|string>}>  $changes  keyed by role id
     * @return array{roles: array<int, array{added: list<string>, removed: list<string>}>, summary: array{granted: int, revoked: int, roles_changed: int, high_risk_granted: int}}
     */
    public function handle(array $changes, bool $confirmed = false): array
    {
        app(ActorContext::class)->assertKnownActor();

        $result = $this->transaction(function () use ($changes, $confirmed): array {
            app(OwnerCounter::class)->lockSuperRole();

            $roleIds = array_map('intval', array_keys($changes));
            sort($roleIds);

            // Fixed id order keeps concurrent matrix writes from deadlocking.
            $roles = Role::query()->whereIn('id', $roleIds)->orderBy('id')->lockForUpdate()->get()->keyBy(fn (Role $role): int => (int) $role->getKey());

            if ($roles->count() !== count($roleIds)) {
                throw (new ModelNotFoundException)->setModel(Role::class);
            }

            $planner = app(RolePermissionPlanner::class);

            $references = [];
            foreach ($changes as $change) {
                array_push($references, ...($change['grant'] ?? []), ...($change['revoke'] ?? []));
            }

            $resolved = $planner->resolve($references);
            $held = $planner->heldIds($roleIds);

            $plans = [];
            foreach ($roleIds as $roleId) {
                $plans[$roleId] = $planner->plan($roles->get($roleId) ?? throw (new ModelNotFoundException)->setModel(Role::class), $changes[$roleId]['grant'] ?? [], $changes[$roleId]['revoke'] ?? [], $resolved, $held[$roleId]);
                $planner->assert($plans[$roleId], $confirmed);
            }

            $applied = [];
            $summary = ['granted' => 0, 'revoked' => 0, 'roles_changed' => 0, 'high_risk_granted' => 0];

            foreach ($plans as $roleId => $plan) {
                $applied[$roleId] = ['added' => [], 'removed' => []];

                if ($plan->isEmpty()) {
                    continue;
                }

                $applied[$roleId] = app(UpdateRolePermissions::class)->handle($plan->role, $changes[$roleId]['grant'] ?? [], $changes[$roleId]['revoke'] ?? [], $confirmed);
                $summary['granted'] += count($applied[$roleId]['added']);
                $summary['revoked'] += count($applied[$roleId]['removed']);
                $summary['roles_changed']++;
                $summary['high_risk_granted'] += count(array_filter($plan->added, static fn ($permission): bool => $permission->risk === 'high'));
            }

            return ['roles' => $applied, 'summary' => $summary];
        });

        if ($result['summary']['roles_changed'] > 0) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $result;
    }
}
