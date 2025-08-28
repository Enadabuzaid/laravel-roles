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

    /*
    |--------------------------------------------------------------------------
    | Seed data
    |--------------------------------------------------------------------------
    */
    'seed' => [
        'roles' => ['manager'], // additional roles to seed
        'permission_groups' => [
            'users' => ['list', 'create', 'update', 'delete', 'restore', 'force-delete'],
            'roles' => ['list','create','update','delete' , 'restore', 'force-delete'],
        ],
        'map' => [
            'super-admin' => ['*'],
            'admin' => ['users.*'],
        ],
    ],
];