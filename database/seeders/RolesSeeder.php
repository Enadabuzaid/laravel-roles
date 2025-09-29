<?php

namespace Enadabuzaid\LaravelRoles\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('roles.guard', 'web');

        // 1) Base roles (package defaults) + merge with config
        $baseRoles = ['super-admin', 'admin', 'user'];
        $configRoles = (array) config('roles.seed.roles', []);
        $roles = array_values(array_unique(array_merge($baseRoles, $configRoles)));

        foreach ($roles as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        // 2a) Flat permissions
        $flatPerms = (array) config('roles.seed.permissions', []);
        foreach ($flatPerms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // 2b) Grouped permissions → "<group>.<action>"
        $groups = (array) config('roles.seed.permission_groups', []);
        foreach ($groups as $group => $actions) {
            foreach ((array) $actions as $action) {
                $permName = "{$group}.{$action}";
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => $guard]);
            }
        }

        // 3) Map permissions to roles (supports '*', 'group.*', explicit slugs)
        $map = (array) config('roles.seed.map', []);
        foreach ($map as $roleName => $permList) {
            $role = Role::where(['name' => $roleName, 'guard_name' => $guard])->first();
            if (! $role) continue;

            $expanded = [];
            foreach ((array) $permList as $perm) {
                if ($perm === '*') {
                    $expanded = Permission::where('guard_name', $guard)->pluck('name')->all();
                    break;
                }
                if (str_ends_with($perm, '.*')) {
                    $prefix = rtrim($perm, '.*');
                    $expanded = array_merge(
                        $expanded,
                        Permission::where('guard_name', $guard)
                            ->where('name', 'like', $prefix . '.%')
                            ->pluck('name')
                            ->all()
                    );
                } else {
                    $expanded[] = $perm;
                }
            }

            $role->syncPermissions(array_values(array_unique($expanded)));
        }
    }
}