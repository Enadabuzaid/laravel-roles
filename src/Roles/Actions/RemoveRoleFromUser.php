<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\RoleChangeSeverity;
use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class RemoveRoleFromUser extends Action
{
    public function handle(AccessUser&Model $user, string|Role $role): void
    {
        $this->transaction(function () use ($user, $role): void {
            /** @var Role $roleModel */
            $roleModel = $role instanceof Role ? $role : Role::findByName($role, (string) config('roles.guard'));
            app(OwnerCounter::class)->lockSuperRole();
            $before = $user->getRoleNames()->all();

            $removed = in_array($roleModel->name, $before, true) ? [$roleModel] : [];
            app(Guardrails::class)->assert(new Operation(
                OperationType::RemoveRole,
                actor: app(ActorContext::class)->actor(),
                target: $user,
                removed: $removed,
            ));

            $user->removeRole($roleModel);
            $user->unsetRelation('roles');
            $after = $user->getRoleNames()->all();

            if ($before !== $after) {
                app(AuditRecorder::class)->record('roles', 'removed', $user, ['roles' => $before], ['roles' => $after], ['role' => $roleModel->name], RoleChangeSeverity::for([], $removed));
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
