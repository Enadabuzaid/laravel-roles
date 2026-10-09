<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Support;

use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Support\Facades\DB;

/**
 * Read-only half of a role-permission change: resolves references, diffs against the stored
 * grants and asserts the guardrails. It never writes; the Actions apply a plan.
 */
final class RolePermissionPlanner
{
    /**
     * Resolves permission references (an int is an id, a string is a name) in one query.
     *
     * @param  list<int|string>  $references
     * @return array{id: array<int, Permission>, name: array<string, Permission>}
     *
     * @throws AccessException
     */
    public function resolve(array $references): array
    {
        $ids = [];
        $names = [];

        foreach ($references as $reference) {
            if (is_int($reference)) {
                $ids[$reference] = true;
            } else {
                $names[$reference] = true;
            }
        }

        if ($ids === [] && $names === []) {
            return ['id' => [], 'name' => []];
        }

        // Chunked so a huge reference list can never exceed a database's bind-parameter limit.
        $found = collect();
        foreach (array_chunk(array_keys($ids), 500) as $chunk) {
            $found = $found->concat(Permission::query()->where('guard_name', (string) config('roles.guard'))->whereIn('id', $chunk)->get());
        }
        foreach (array_chunk(array_map('strval', array_keys($names)), 500) as $chunk) {
            $found = $found->concat(Permission::query()->where('guard_name', (string) config('roles.guard'))->whereIn('name', $chunk)->get());
        }

        $resolved = ['id' => [], 'name' => []];
        foreach ($found as $permission) {
            $resolved['id'][(int) $permission->getKey()] = $permission;
            $resolved['name'][$permission->name] = $permission;
        }

        $missing = [];
        foreach ($references as $reference) {
            if ($this->lookup($resolved, $reference) === null) {
                $missing[] = $reference;
            }
        }

        if ($missing !== []) {
            throw new AccessException('Unknown permission.', 'permission.not_found', 404, ['permissions' => array_values(array_unique($missing, SORT_REGULAR))]);
        }

        return $resolved;
    }

    /**
     * Permission ids currently granted to each role (one pivot query).
     *
     * @param  list<int>  $roleIds
     * @return array<int, array<int, true>>
     */
    public function heldIds(array $roleIds): array
    {
        $tables = (array) config('permission.table_names');
        $held = array_fill_keys($roleIds, []);

        if ($roleIds === []) {
            return $held;
        }

        foreach (array_chunk($roleIds, 500) as $chunk) {
            foreach (DB::table((string) $tables['role_has_permissions'])->whereIn('role_id', $chunk)->get(['role_id', 'permission_id']) as $row) {
                $held[(int) $row->role_id][(int) $row->permission_id] = true;
            }
        }

        return $held;
    }

    /**
     * @param  list<int|string>  $grant
     * @param  list<int|string>  $revoke
     * @param  array{id: array<int, Permission>, name: array<string, Permission>}  $resolved
     * @param  array<int, true>  $held
     *
     * @throws AccessException
     */
    public function plan(Role $role, array $grant, array $revoke, array $resolved, array $held): RolePermissionPlan
    {
        $grantIds = $this->unique($grant, $resolved);
        $revokeIds = $this->unique($revoke, $resolved);

        if (array_intersect_key($grantIds, $revokeIds) !== []) {
            throw new AccessException('A permission cannot be granted and revoked in the same change.', 'permission.conflicting_changes', 422);
        }

        $added = [];
        foreach ($grantIds as $id => $permission) {
            if (isset($held[$id])) {
                continue;
            }

            if ($permission->deprecated_at !== null) {
                throw new AccessException("The permission {$permission->name} is deprecated and can't be granted.", 'permission.deprecated', 409);
            }

            $added[] = $permission;
        }

        $removed = [];
        foreach ($revokeIds as $id => $permission) {
            if (isset($held[$id])) {
                $removed[] = $permission;
            }
        }

        return new RolePermissionPlan($role, $added, $removed);
    }

    /**
     * @throws AccessException
     */
    public function assert(RolePermissionPlan $plan, bool $confirmed): void
    {
        app(Guardrails::class)->assert(new Operation(
            OperationType::UpdateRolePermissions,
            actor: app(ActorContext::class)->actor(),
            role: $plan->role,
            confirmed: $confirmed,
            addedPermissions: $plan->added,
            removedPermissions: $plan->removed,
        ));
    }

    /**
     * @param  list<int|string>  $references
     * @param  array{id: array<int, Permission>, name: array<string, Permission>}  $resolved
     * @return array<int, Permission>
     */
    private function unique(array $references, array $resolved): array
    {
        $unique = [];

        foreach ($references as $reference) {
            $permission = $this->lookup($resolved, $reference) ?? throw new AccessException('Unknown permission.', 'permission.not_found', 404);
            $unique[(int) $permission->getKey()] = $permission;
        }

        return $unique;
    }

    /**
     * @param  array{id: array<int, Permission>, name: array<string, Permission>}  $resolved
     */
    private function lookup(array $resolved, int|string $reference): ?Permission
    {
        return is_int($reference) ? ($resolved['id'][$reference] ?? null) : ($resolved['name'][$reference] ?? null);
    }
}
