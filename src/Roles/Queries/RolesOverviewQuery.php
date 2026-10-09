<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Queries;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RolesOverviewQuery
{
    /**
     * total_roles counts every role including trashed; the other role counts cover non-trashed roles only.
     *
     * @return array{total_roles: int, active_roles: int, system_roles: int, custom_roles: int, trashed_roles: int, members_with_role: int, members_without_role: int, avg_permissions_per_role: float}
     */
    public static function summary(): array
    {
        $tables = (array) config('permission.table_names');
        $roles = (string) ($tables['roles'] ?? 'roles');
        $modelHasRoles = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
        $roleHasPermissions = (string) ($tables['role_has_permissions'] ?? 'role_has_permissions');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');
        $user = new $userModel;
        $morphClass = $user->getMorphClass();

        $counts = DB::table($roles)->selectRaw(
            'count(*) as total, '
            .'sum(case when deleted_at is null then 1 else 0 end) as active, '
            .'sum(case when deleted_at is null and is_system then 1 else 0 end) as system_roles, '
            .'sum(case when deleted_at is null and not is_system then 1 else 0 end) as custom_roles'
        )->first();

        $holders = DB::table($modelHasRoles)->where('model_type', $morphClass);
        $withRole = $userModel::query()->whereIn($user->getQualifiedKeyName(), (clone $holders)->select($morphKey))->count();

        $withoutRole = $userModel::query()
            ->whereNotIn($user->getQualifiedKeyName(), (clone $holders)->select($morphKey))
            ->count();

        $active = (int) ($counts->active ?? 0);
        $assigned = DB::table($roleHasPermissions)
            ->join($roles, $roles.'.id', '=', $roleHasPermissions.'.role_id')
            ->whereNull($roles.'.deleted_at')
            ->count();

        return [
            'total_roles' => (int) ($counts->total ?? 0),
            'active_roles' => $active,
            'system_roles' => (int) ($counts->system_roles ?? 0),
            'custom_roles' => (int) ($counts->custom_roles ?? 0),
            'trashed_roles' => (int) ($counts->total ?? 0) - $active,
            'members_with_role' => $withRole,
            'members_without_role' => $withoutRole,
            'avg_permissions_per_role' => $active > 0 ? round($assigned / $active, 2) : 0.0,
        ];
    }
}
