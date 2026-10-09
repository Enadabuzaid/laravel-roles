<?php

declare(strict_types=1);
use Enadstack\LaravelRoles\Core\Doctor\Checks\AuditReadyCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\CacheWorkingCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\ConfigConsistencyCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\DeprecatedStillHeldCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\GuardrailConfigCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\NoEmptyRolesCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\OwnerExistsCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\PermissionsSyncedCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\RoutesProtectedCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\UserModelCheck;
use Enadstack\LaravelRoles\Guardrails\Rules\DestructiveConfirmationRule;
use Enadstack\LaravelRoles\Guardrails\Rules\EscalationRule;
use Enadstack\LaravelRoles\Guardrails\Rules\HighRiskConfirmRule;
use Enadstack\LaravelRoles\Guardrails\Rules\LastOwnerRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfChangeRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfDestructiveRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SystemRoleRule;

return [
    'user_model' => env('ROLES_USER_MODEL', 'App\\Models\\User'),
    'guard' => env('ROLES_GUARD', 'web'),
    'teams' => false, // unsupported in 2.0-alpha: must stay false (doctor fails otherwise)
    'id_type' => 'int', // unsupported in 2.0-alpha: must stay int (uuid | ulid planned)
    'super_role' => 'owner',

    'default_locale' => 'en',
    'fallback_locale' => 'en',
    'locales' => ['en', 'ar'],

    'modules' => [
        'users' => true, 'roles' => true, 'permissions' => true,
        'security' => true, 'audit' => true, 'reports' => true,
        'invitations' => true,
    ],

    'audit' => ['enabled' => true],

    'routes' => [
        'prefix' => 'api/access',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    'http' => ['rate_limit' => '60,1'], // throttle:<attempts>,<minutes> on every package route

    'permissions_file' => base_path('access/permissions.php'),

    'guardrails' => [
        'min_owners' => 1,
        'prevent_self_escalation' => true,
        'prevent_self_destructive' => true,
        'prevent_escalation' => true,
        'confirm_high_risk' => true,
        'type_to_confirm_destructive' => true,
        'disabled' => [], // rule classes to switch off (a security decision)
        'rules' => [
            SystemRoleRule::class,
            SelfChangeRule::class,
            SelfDestructiveRule::class,
            EscalationRule::class,
            LastOwnerRule::class,
            HighRiskConfirmRule::class,
            DestructiveConfirmationRule::class,
        ],
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
            ConfigConsistencyCheck::class,
            UserModelCheck::class,
            OwnerExistsCheck::class,
            PermissionsSyncedCheck::class,
            RoutesProtectedCheck::class,
            NoEmptyRolesCheck::class,
            DeprecatedStillHeldCheck::class,
            CacheWorkingCheck::class,
            AuditReadyCheck::class,
            GuardrailConfigCheck::class,
        ],
        'disabled' => [],
    ],
];
