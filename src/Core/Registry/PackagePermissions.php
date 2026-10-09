<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Registry;

final class PackagePermissions
{
    /** @var array<string, array<string, array{0: string, 1: string, 2?: string}>> module => name => [en, ar, risk?] */
    private const TABLE = [
        'users' => [
            'access.users.view' => ['View users', 'عرض المستخدمين'],
            'access.users.create' => ['Create users', 'إنشاء مستخدمين'],
            'access.users.update' => ['Edit users', 'تعديل المستخدمين'],
            'access.users.suspend' => ['Suspend users', 'إيقاف المستخدمين', 'high'],
            'access.users.delete' => ['Delete users', 'حذف المستخدمين', 'high'],
        ],
        'roles' => [
            'access.roles.view' => ['View roles', 'عرض الأدوار'],
            'access.roles.create' => ['Create roles', 'إنشاء أدوار'],
            'access.roles.update' => ['Edit roles', 'تعديل الأدوار'],
            'access.roles.delete' => ['Delete roles', 'حذف الأدوار', 'high'],
            'access.roles.assign' => ['Assign roles', 'إسناد الأدوار', 'medium'],
        ],
        'permissions' => [
            'access.permissions.view' => ['View permissions', 'عرض الصلاحيات'],
            'access.permissions.sync' => ['Sync permissions', 'مزامنة الصلاحيات', 'high'],
        ],
        'security' => [
            'access.security.view' => ['View security', 'عرض الأمان'],
            'access.security.manage' => ['Manage security', 'إدارة الأمان', 'high'],
        ],
        'audit' => [
            'access.audit.view' => ['View audit log', 'عرض سجل التدقيق'],
        ],
        'reports' => [
            'access.reports.view' => ['View reports', 'عرض التقارير'],
            'access.reports.export' => ['Export reports', 'تصدير التقارير'],
        ],
        'invitations' => [
            'access.invitations.view' => ['View invitations', 'عرض الدعوات'],
            'access.invitations.create' => ['Send invitations', 'إرسال دعوات'],
            'access.invitations.revoke' => ['Revoke invitations', 'إلغاء الدعوات'],
        ],
    ];

    /**
     * @param  list<string>  $enabledModules
     * @return list<PermissionDefinition>
     */
    public static function definitions(array $enabledModules): array
    {
        $definitions = [];

        foreach (self::TABLE as $module => $permissions) {
            if (! in_array($module, $enabledModules, true)) {
                continue;
            }

            $order = 0;
            foreach ($permissions as $name => $spec) {
                $definitions[] = new PermissionDefinition(
                    name: $name,
                    module: $module,
                    label: ['en' => $spec[0], 'ar' => $spec[1]],
                    description: null,
                    risk: $spec[2] ?? 'low',
                    sortOrder: ++$order,
                );
            }
        }

        return $definitions;
    }
}
