<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Support;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\DB;

final class PermissionHolders
{
    /**
     * Role and direct user grants of the permission, trashed roles included.
     */
    public static function count(Permission $permission): int
    {
        $tables = (array) config('permission.table_names');
        $key = (string) (config('permission.column_names.permission_pivot_key') ?: 'permission_id');

        return (int) DB::table((string) $tables['role_has_permissions'])->where($key, $permission->getKey())->count()
            + (int) DB::table((string) $tables['model_has_permissions'])->where($key, $permission->getKey())->count();
    }
}
