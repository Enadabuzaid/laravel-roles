<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Queries;

use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MatrixQuery
{
    /**
     * Exactly three queries however large the matrix is: roles, permissions (active, plus deprecated ones
     * still held by a live role) and the grant pivot rows.
     *
     * `locked` marks columns the actor can't edit: the owner role, a role the actor holds (unless they are an
     * owner) and every role when the actor may not update roles.
     *
     * @return array{modules: list<array{key: string, label: string, permissions: list<array<string, mixed>>}>, roles: list<array<string, mixed>>, summary: array{roles: int, permissions: int, grants: int}}
     */
    public static function get(?Model $actor, bool $canUpdate): array
    {
        $tables = (array) config('permission.table_names');
        $pivot = (string) ($tables['role_has_permissions'] ?? 'role_has_permissions');
        $rolesTable = (string) ($tables['roles'] ?? 'roles');
        $guard = (string) config('roles.guard');

        $roles = Role::query()->where('guard_name', $guard)->orderBy('sort_order')->orderBy('name')->get();

        $heldByLiveRole = DB::table($pivot)
            ->join($rolesTable, $rolesTable.'.id', '=', $pivot.'.role_id')
            ->whereNull($rolesTable.'.deleted_at')
            ->select($pivot.'.permission_id');

        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->where(fn ($query) => $query->whereNull('deprecated_at')->orWhereIn('id', $heldByLiveRole))
            ->orderBy('module')->orderBy('sort_order')->orderBy('name')
            ->get();

        $grants = [];
        foreach (DB::table($pivot)->whereIn('role_id', Role::query()->where('guard_name', $guard)->select('id'))->get(['role_id', 'permission_id']) as $row) {
            $grants[(int) $row->role_id][] = (int) $row->permission_id;
        }

        $heldRoleIds = self::heldRoleIds($actor);
        $actorIsOwner = $actor !== null && app(OwnerCounter::class)->isOwner($actor);

        $roleRows = array_values($roles->map(function (Role $role) use ($grants, $heldRoleIds, $actorIsOwner, $canUpdate): array {
            $isOwner = RoleRisk::isOwnerRole($role);

            return [
                'id' => $role->getKey(),
                'name' => $role->name,
                'label_text' => $role->label_text,
                'color' => $role->getAttribute('color'),
                'is_system' => (bool) $role->is_system,
                'is_owner' => $isOwner,
                'locked' => $isOwner || ! $canUpdate || (! $actorIsOwner && isset($heldRoleIds[(int) $role->getKey()])),
                'permission_ids' => $grants[(int) $role->getKey()] ?? [],
            ];
        })->all());

        $modules = [];
        foreach ($permissions as $permission) {
            $key = $permission->module ?? 'other';
            $modules[$key] ??= ['key' => $key, 'label' => Str::headline($key), 'permissions' => []];
            $modules[$key]['permissions'][] = [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'label_text' => $permission->label_text,
                'label' => $permission->label,
                'risk' => $permission->risk,
                'deprecated' => $permission->deprecated_at !== null,
            ];
        }

        return [
            'modules' => array_values($modules),
            'roles' => $roleRows,
            'summary' => ['roles' => count($roleRows), 'permissions' => $permissions->count(), 'grants' => array_sum(array_map('count', $grants))],
        ];
    }

    /**
     * @return array<int, true>
     */
    private static function heldRoleIds(?Model $actor): array
    {
        if ($actor === null || ! method_exists($actor, 'roles')) {
            return [];
        }

        $held = [];
        foreach ($actor->roles as $role) { // @phpstan-ignore property.notFound
            $held[(int) $role->getKey()] = true;
        }

        return $held;
    }
}
