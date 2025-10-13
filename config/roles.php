<?php

return [

    /*
    |--------------------------------------------------------------------------
    | i18n (Languages)
    |--------------------------------------------------------------------------
    |
    | During install we'll ask you to select one or more locales.
    | If multiple locales are chosen, 'enabled' = true and we store them here.
    |
    */
    'i18n' => [
        'enabled' => false,
        'locales' => ['en'],      // e.g., ['en','ar']
        'default' => 'en',
        'fallback' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default guard
    |--------------------------------------------------------------------------
    */
    'guard' => env('ROLES_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Tenancy mode
    |--------------------------------------------------------------------------
    |
    | 'single'         : no multi-tenancy
    | 'team_scoped'    : one database; scope by team/tenant FK (Spatie "teams")
    | 'multi_database' : each tenant/provider has its own database
    |
    */
    'tenancy' => [
        'mode' => 'single',
        'team_foreign_key' => 'team_id', // only for team_scoped
        'provider' => null,              // e.g., 'stancl/tenancy' for multi_database
    ],


    'routes' => [
        'prefix' => 'admin/acl', // acl : Access Control List
        'middleware' => ['api', 'auth:sanctum'], // add your 'is_admin' middleware too if needed
        'guard' => env('ROLES_GUARD', 'web'), // guard for the routes
    ],

    /*
    |--------------------------------------------------------------------------
    | Seed data
    |--------------------------------------------------------------------------
    */
    'seed' => [
        'roles' => ['manager'], // additional roles to seed
        'permission_groups' => [
            'roles' => ['list','create', 'show' ,'update','delete' , 'restore', 'force-delete'],
            'users' => ['list', 'create', 'show', 'update', 'delete', 'restore', 'force-delete'],
            'permissions' => ['list' , 'show']

        ],
        'map' => [
            'super-admin' => ['*'],
            'admin' => ['users.*'],
        ],
        'role_descriptions' => [
            'super-admin' => 'Full system access',
            'admin'       => 'Manage users and content',
            'user'        => 'Standard account',
            'manager'     => 'Operations management',
        ],
        'permission_descriptions' => [
            'roles.list' => 'Allow listing of roles',
            'roles.create' => 'Allow creating new roles',
            'roles.show' => 'Allow viewing role details',
            'roles.update' => 'Allow updating existing roles',
            'roles.delete' => 'Allow deleting roles',
            'roles.restore' => 'Allow restoring deleted roles',
            'roles.force-delete' => 'Allow permanently deleting roles',
            'users.list' => 'Allow listing of users',
            'users.create' => 'Allow creating new users',
            'users.show' => 'Allow viewing user details',
            'users.update' => 'Allow updating existing users',
            'users.delete' => 'Allow deleting users',
            'users.restore' => 'Allow restoring deleted users',
            'users.force-delete' => 'Allow permanently deleting users',
            'permissions.list' => 'Allow listing of permissions',
            'permissions.show' => 'Allow viewing permission details',
        ],
    ],
];