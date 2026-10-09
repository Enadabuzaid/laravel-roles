<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails;

enum OperationType: string
{
    case AssignRole = 'assign_role';
    case RemoveRole = 'remove_role';
    case SyncRoles = 'sync_roles';
    case DeleteRole = 'delete_role';
    case ForceDeleteRole = 'force_delete_role';
    case DeletePermission = 'delete_permission';
    case RenameRole = 'rename_role';
    case RestoreRole = 'restore_role';
    case UpdateRolePermissions = 'update_role_permissions';
    case SuspendUser = 'suspend_user';
    case DeactivateUser = 'deactivate_user';
    case DeleteUser = 'delete_user';

    public function isRoleChange(): bool
    {
        return in_array($this, [self::AssignRole, self::RemoveRole, self::SyncRoles], true);
    }

    public function isUserDestructive(): bool
    {
        return in_array($this, [self::SuspendUser, self::DeactivateUser, self::DeleteUser], true);
    }
}
