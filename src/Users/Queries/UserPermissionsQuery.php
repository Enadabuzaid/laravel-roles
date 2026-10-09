<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Queries;

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Users\Support\TargetStanding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class UserPermissionsQuery
{
    /**
     * The entries for the user with the given id (404 when there is none) and whether they hold the owner role.
     *
     * @return array{entries: list<array{permission: Permission, roles: list<string>, direct: bool}>, is_owner: bool}
     *
     * @throws ModelNotFoundException
     */
    public static function forUserId(string|int $id): array
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');
        $user = $userModel::query()->findOrFail($id);

        return ['entries' => self::entries($user), 'is_owner' => app(TargetStanding::class)->ownerKeys($user, [$user->getKey()]) !== []];
    }

    /**
     * Effective permissions of the guard (through live roles, plus direct), with where each comes from.
     * Three queries however many roles and permissions the user has.
     *
     * @return list<array{permission: Permission, roles: list<string>, direct: bool}> ordered by module then name
     */
    public static function entries(Model $user): array
    {
        $sources = self::sources($user);
        $ids = array_keys($sources);

        if ($ids === []) {
            return [];
        }

        $entries = [];
        foreach (Permission::query()->whereKey($ids)->get() as $permission) {
            $source = $sources[$permission->getKey()];
            $roles = array_keys($source['roles']);
            sort($roles);
            $entries[] = ['permission' => $permission, 'roles' => $roles, 'direct' => $source['direct']];
        }

        usort($entries, static fn (array $a, array $b): int => [(string) ($a['permission']->module ?? 'other'), $a['permission']->name] <=> [(string) ($b['permission']->module ?? 'other'), $b['permission']->name]);

        return $entries;
    }

    /** Distinct effective permissions of the user (two queries). */
    public static function count(Model $user): int
    {
        return count(self::sources($user));
    }

    /** @return array<int, array{roles: array<string, true>, direct: bool}> keyed by permission id */
    private static function sources(Model $user): array
    {
        $tables = (array) config('permission.table_names');
        $roles = (string) ($tables['roles'] ?? 'roles');
        $permissions = (string) ($tables['permissions'] ?? 'permissions');
        $modelRoles = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
        $modelPermissions = (string) ($tables['model_has_permissions'] ?? 'model_has_permissions');
        $rolePermissions = (string) ($tables['role_has_permissions'] ?? 'role_has_permissions');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $guard = (string) config('roles.guard');

        $viaRoles = DB::table($modelRoles)
            ->join($roles, $roles.'.id', '=', $modelRoles.'.role_id')
            ->join($rolePermissions, $rolePermissions.'.role_id', '=', $roles.'.id')
            ->join($permissions, $permissions.'.id', '=', $rolePermissions.'.permission_id')
            ->where($modelRoles.'.model_type', $user->getMorphClass())
            ->where($modelRoles.'.'.$morphKey, $user->getKey())
            ->where($roles.'.guard_name', $guard)
            ->whereNull($roles.'.deleted_at')
            ->where($permissions.'.guard_name', $guard)
            ->get([$permissions.'.id as permission_id', $roles.'.name as role_name']);

        $direct = DB::table($modelPermissions)
            ->join($permissions, $permissions.'.id', '=', $modelPermissions.'.permission_id')
            ->where($modelPermissions.'.model_type', $user->getMorphClass())
            ->where($modelPermissions.'.'.$morphKey, $user->getKey())
            ->where($permissions.'.guard_name', $guard)
            ->pluck($permissions.'.id');

        $sources = [];
        foreach ($viaRoles as $row) {
            $sources[(int) $row->permission_id] ??= ['roles' => [], 'direct' => false];
            $sources[(int) $row->permission_id]['roles'][(string) $row->role_name] = true;
        }

        foreach ($direct as $id) {
            $sources[(int) $id] ??= ['roles' => [], 'direct' => false];
            $sources[(int) $id]['direct'] = true;
        }

        return $sources;
    }
}
