<?php

declare(strict_types=1);

namespace Tests\Traits;

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

/**
 * SeedsRolesAndPermissions Trait
 *
 * Seeds test data for roles and permissions.
 */
trait SeedsRolesAndPermissions
{
    /**
     * Seed default roles.
     *
     * @param string $guard
     * @return array
     */
    protected function seedDefaultRoles(string $guard = 'web'): array
    {
        return [
            'admin' => Role::create([
                'name' => 'admin',
                'guard_name' => $guard,
            ]),
            'editor' => Role::create([
                'name' => 'editor',
                'guard_name' => $guard,
            ]),
            'viewer' => Role::create([
                'name' => 'viewer',
                'guard_name' => $guard,
            ]),
        ];
    }

    /**
     * Seed default permissions.
     *
     * @param string $guard
     * @return array
     */
    protected function seedDefaultPermissions(string $guard = 'web'): array
    {
        $permissions = [];

        $groups = [
            'users' => ['list', 'create', 'show', 'update', 'delete'],
            'roles' => ['list', 'create', 'show', 'update', 'delete'],
            'posts' => ['list', 'create', 'show', 'update', 'delete'],
        ];

        foreach ($groups as $group => $actions) {
            foreach ($actions as $action) {
                $name = "{$group}.{$action}";
                $permissions[$name] = Permission::create([
                    'name' => $name,
                    'guard_name' => $guard,
                    'group' => $group,
                ]);
            }
        }

        return $permissions;
    }

    /**
     * Seed roles with permissions.
     *
     * @param string $guard
     * @return array
     */
    protected function seedRolesWithPermissions(string $guard = 'web'): array
    {
        $roles = $this->seedDefaultRoles($guard);
        $permissions = $this->seedDefaultPermissions($guard);

        // Admin gets all permissions
        $roles['admin']->syncPermissions(array_values($permissions));

        // Editor gets users and posts permissions
        $editorPerms = array_filter($permissions, function ($key) {
            return str_starts_with($key, 'users.') || str_starts_with($key, 'posts.');
        }, ARRAY_FILTER_USE_KEY);
        $roles['editor']->syncPermissions(array_values($editorPerms));

        // Viewer gets only list/show permissions
        $viewerPerms = array_filter($permissions, function ($key) {
            return str_ends_with($key, '.list') || str_ends_with($key, '.show');
        }, ARRAY_FILTER_USE_KEY);
        $roles['viewer']->syncPermissions(array_values($viewerPerms));

        return [
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    /**
     * Create a role with specific permissions.
     *
     * @param string $name
     * @param array $permissionNames
     * @param string $guard
     * @return Role
     */
    protected function createRoleWithPermissions(
        string $name,
        array $permissionNames,
        string $guard = 'web'
    ): Role {
        $role = Role::create([
            'name' => $name,
            'guard_name' => $guard,
        ]);

        $permissions = [];
        foreach ($permissionNames as $permName) {
            $permissions[] = Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => $guard,
            ]);
        }

        $role->syncPermissions($permissions);

        return $role;
    }
}
