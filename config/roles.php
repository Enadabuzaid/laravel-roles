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
    | Admin UI
    |--------------------------------------------------------------------------
    |
    | Configure the optional Vue/Inertia admin UI.
    | Set 'enabled' to true to activate the UI routes.
    | Set 'driver' to 'vue' for Vue 3 + Inertia.js.
    |
    */
    'ui' => [
        'enabled' => env('ROLES_UI_ENABLED', false),
        'driver' => 'vue', // 'vue' or 'blade' (future)
        'prefix' => 'admin/acl', // UI route prefix
        'middleware' => ['web', 'auth'], // UI-specific middleware (different from API)
        'layout' => 'AppLayout', // Your app's main layout component
    ],



    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Configure the API routes for roles and permissions.
    |
    | Middleware options:
    | - ['web', 'auth'] : For session-based apps (Inertia, Blade)
    | - ['api', 'auth:sanctum'] : For token-based API access
    |
    */
    'routes' => [
        'prefix' => 'admin/acl', // acl : Access Control List
        'middleware' => ['web', 'auth'], // default for session-based apps
        'guard' => env('ROLES_GUARD', 'web'), // guard for the routes
        // Expose handy endpoints for the authenticated user's roles/permissions
        'expose_me' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache heavy computations like grouped permissions and permission matrix.
    | If your cache driver supports tags, they will be used automatically.
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // seconds
        'keys' => [
            'grouped_permissions' => 'laravel_roles.grouped_permissions',
            'permission_matrix' => 'laravel_roles.permission_matrix',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Seed data
    |--------------------------------------------------------------------------
    */
    'seed' => [
        'roles' => ['manager'], // additional roles to seed

        // Seeder classes to run (in order)
        'seeders' => [
            \Enadstack\LaravelRoles\Database\Seeders\RolesSeeder::class,
            \Enadstack\LaravelRoles\Database\Seeders\SuperAdminSeeder::class,
            \Enadstack\LaravelRoles\Database\Seeders\AdminSeeder::class,
        ],

        // Super Admin User Configuration
        'super_admin' => [
            'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@example.com'),
            'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
            'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        ],

        // Admin User Configuration
        'admin' => [
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
            'password' => env('ADMIN_PASSWORD', 'password'),
            'name' => env('ADMIN_NAME', 'Admin'),
        ],

        'permission_groups' => [
            'roles' => ['list','create', 'show' ,'update','delete' , 'restore', 'force-delete'],
            'users' => ['list', 'create', 'show', 'update', 'delete', 'restore', 'force-delete'],
            'permissions' => ['list' , 'show']

        ],
        'map' => [
            'super-admin' => ['*'],
            'admin' => ['users.*' , 'roles.*' , 'permissions.*' ],
        ],
        'role_descriptions' => [
            'super-admin' => 'Full system access',
            'admin'       => 'Manage users and content',
            'user'        => 'Standard account',
            'manager'     => 'Operations management',
        ],
        'permission_descriptions' => [
            // Single language: use string
            // Multi language (i18n): use array with locale keys
            'roles.list' => [
                'en' => 'Allow listing of roles',
                'ar' => 'السماح بعرض قائمة الأدوار',
            ],
            'roles.create' => [
                'en' => 'Allow creating new roles',
                'ar' => 'السماح بإنشاء أدوار جديدة',
            ],
            'roles.show' => [
                'en' => 'Allow viewing role details',
                'ar' => 'السماح بعرض تفاصيل الدور',
            ],
            'roles.update' => [
                'en' => 'Allow updating existing roles',
                'ar' => 'السماح بتحديث الأدوار الموجودة',
            ],
            'roles.delete' => [
                'en' => 'Allow deleting roles',
                'ar' => 'السماح بحذف الأدوار',
            ],
            'roles.restore' => [
                'en' => 'Allow restoring deleted roles',
                'ar' => 'السماح باستعادة الأدوار المحذوفة',
            ],
            'roles.force-delete' => [
                'en' => 'Allow permanently deleting roles',
                'ar' => 'السماح بحذف الأدوار نهائياً',
            ],
            'users.list' => [
                'en' => 'Allow listing of users',
                'ar' => 'السماح بعرض قائمة المستخدمين',
            ],
            'users.create' => [
                'en' => 'Allow creating new users',
                'ar' => 'السماح بإنشاء مستخدمين جدد',
            ],
            'users.show' => [
                'en' => 'Allow viewing user details',
                'ar' => 'السماح بعرض تفاصيل المستخدم',
            ],
            'users.update' => [
                'en' => 'Allow updating existing users',
                'ar' => 'السماح بتحديث المستخدمين الموجودين',
            ],
            'users.delete' => [
                'en' => 'Allow deleting users',
                'ar' => 'السماح بحذف المستخدمين',
            ],
            'users.restore' => [
                'en' => 'Allow restoring deleted users',
                'ar' => 'السماح باستعادة المستخدمين المحذوفين',
            ],
            'users.force-delete' => [
                'en' => 'Allow permanently deleting users',
                'ar' => 'السماح بحذف المستخدمين نهائياً',
            ],
            'permissions.list' => [
                'en' => 'Allow listing of permissions',
                'ar' => 'السماح بعرض قائمة الصلاحيات',
            ],
            'permissions.show' => [
                'en' => 'Allow viewing permission details',
                'ar' => 'السماح بعرض تفاصيل الصلاحية',
            ],
        ],
        'permission_labels' => [
            'roles.list' => [
                'en' => 'List Roles',
                'ar' => 'عرض الأدوار',
            ],
            'roles.create' => [
                'en' => 'Create Role',
                'ar' => 'إنشاء دور',
            ],
            'roles.show' => [
                'en' => 'View Role',
                'ar' => 'عرض الدور',
            ],
            'roles.update' => [
                'en' => 'Update Role',
                'ar' => 'تحديث الدور',
            ],
            'roles.delete' => [
                'en' => 'Delete Role',
                'ar' => 'حذف الدور',
            ],
            'roles.restore' => [
                'en' => 'Restore Role',
                'ar' => 'استعادة الدور',
            ],
            'roles.force-delete' => [
                'en' => 'Force Delete Role',
                'ar' => 'حذف الدور نهائياً',
            ],
            'users.list' => [
                'en' => 'List Users',
                'ar' => 'عرض المستخدمين',
            ],
            'users.create' => [
                'en' => 'Create User',
                'ar' => 'إنشاء مستخدم',
            ],
            'users.show' => [
                'en' => 'View User',
                'ar' => 'عرض المستخدم',
            ],
            'users.update' => [
                'en' => 'Update User',
                'ar' => 'تحديث المستخدم',
            ],
            'users.delete' => [
                'en' => 'Delete User',
                'ar' => 'حذف المستخدم',
            ],
            'users.restore' => [
                'en' => 'Restore User',
                'ar' => 'استعادة المستخدم',
            ],
            'users.force-delete' => [
                'en' => 'Force Delete User',
                'ar' => 'حذف المستخدم نهائياً',
            ],
            'permissions.list' => [
                'en' => 'List Permissions',
                'ar' => 'عرض الصلاحيات',
            ],
            'permissions.show' => [
                'en' => 'View Permission',
                'ar' => 'عرض الصلاحية',
            ],
        ],
        'permission_group_labels' => [
            'roles' => [
                'en' => 'Roles',
                'ar' => 'الأدوار',
            ],
            'users' => [
                'en' => 'Users',
                'ar' => 'المستخدمين',
            ],
            'permissions' => [
                'en' => 'Permissions',
                'ar' => 'الصلاحيات',
            ],
        ],
    ],
];