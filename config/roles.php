<?php

declare(strict_types=1);
use Enadstack\LaravelRoles\Core\Doctor\Checks\CacheWorkingCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\DeprecatedStillHeldCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\NoEmptyRolesCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\OwnerExistsCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\PermissionsSyncedCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\RoutesProtectedCheck;

return [
    'user_model' => env('ROLES_USER_MODEL', 'App\\Models\\User'),
    'guard' => env('ROLES_GUARD', 'web'),
    'teams' => false,
    'id_type' => 'int', // int | uuid | ulid — read by migrations added in later phases
    'super_role' => 'owner',

    'default_locale' => 'en',
    'fallback_locale' => 'en',
    'locales' => ['en', 'ar'],

    'modules' => [
        'users' => true, 'roles' => true, 'permissions' => true,
        'security' => true, 'audit' => true, 'reports' => true,
        'invitations' => true,
    ],

    'routes' => [
        'prefix' => 'api/access',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    'permissions_file' => base_path('access/permissions.php'),

    'guardrails' => [
        'min_owners' => 1,
        'prevent_self_escalation' => true,
        'confirm_high_risk' => true,
        'type_to_confirm_destructive' => true,
    ],

    'security' => [
        'max_failed_logins' => 5,
        'lock_minutes' => 15,
        'require_mfa_roles' => ['owner', 'admin'],
        'login_history_days' => 180,
    ],

    'pagination' => ['default' => 15, 'max' => 100],

    'reports' => [],

    'default_roles' => [
        'owner' => [
            'label' => ['en' => 'Owner', 'ar' => 'المالك'],
            'description' => ['en' => 'Full access to everything.', 'ar' => 'صلاحية كاملة على كل شيء.'],
            'color' => '#7c3aed', 'is_system' => true, 'is_default' => false, 'sort_order' => 1,
        ],
        'admin' => [
            'label' => ['en' => 'Admin', 'ar' => 'مدير النظام'],
            'description' => ['en' => 'Manages users and roles.', 'ar' => 'يدير المستخدمين والأدوار.'],
            'color' => '#2563eb', 'is_system' => true, 'is_default' => false, 'sort_order' => 2,
        ],
        'manager' => [
            'label' => ['en' => 'Manager', 'ar' => 'مدير'],
            'description' => ['en' => 'Manages day-to-day operations.', 'ar' => 'يدير العمليات اليومية.'],
            'color' => '#0891b2', 'is_system' => false, 'is_default' => false, 'sort_order' => 3,
        ],
        'viewer' => [
            'label' => ['en' => 'Viewer', 'ar' => 'مشاهد'],
            'description' => ['en' => 'Read-only access.', 'ar' => 'صلاحية قراءة فقط.'],
            'color' => '#64748b', 'is_system' => false, 'is_default' => true, 'sort_order' => 4,
        ],
    ],

    'doctor' => [
        'checks' => [
            OwnerExistsCheck::class,
            PermissionsSyncedCheck::class,
            RoutesProtectedCheck::class,
            NoEmptyRolesCheck::class,
            DeprecatedStillHeldCheck::class,
            CacheWorkingCheck::class,
        ],
    ],
];
