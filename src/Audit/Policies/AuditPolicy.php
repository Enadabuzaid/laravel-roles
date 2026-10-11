<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Policies;

/** Maps audit abilities to the package permissions. Registered for the action log model. */
final class AuditPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allows($user, 'access.audit.view');
    }

    public function view(mixed $user): bool
    {
        return $this->allows($user, 'access.audit.view');
    }

    /** Exporting needs both the audit view and the reports export permission. */
    public function export(mixed $user): bool
    {
        return $this->allows($user, 'access.audit.view') && $this->allows($user, 'access.reports.export');
    }

    private function allows(mixed $user, string $permission): bool
    {
        return is_object($user) && method_exists($user, 'checkPermissionTo') && $user->checkPermissionTo($permission);
    }
}
