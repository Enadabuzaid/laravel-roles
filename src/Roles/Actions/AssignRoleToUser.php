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

final class AssignRoleToUser extends Action
{
    public function handle(AccessUser&Model $user, string|Role $role, bool $confirmed = false): void
    {
        $this->transaction(function () use ($user, $role, $confirmed): void {
            /** @var Role $roleModel */
            $roleModel = $role instanceof Role ? $role : Role::findByName($role, (string) config('roles.guard'));
            app(OwnerCounter::class)->lockSuperRole();
            // Fresh snapshot: a relation loaded earlier may be stale relative to the locked DB state.
            $user->unsetRelation('roles');
            $before = $user->getRoleNames()->all();

            $added = in_array($roleModel->name, $before, true) ? [] : [$roleModel];
            app(Guardrails::class)->assert(new Operation(
                OperationType::AssignRole,
                actor: app(ActorContext::class)->actor(),
                target: $user,
                added: $added,
                confirmed: $confirmed,
            ));

            $user->assignRole($roleModel);
            $user->unsetRelation('roles');
            $after = $user->getRoleNames()->all();

            if ($before !== $after) {
                app(AuditRecorder::class)->record('roles', 'assigned', $user, ['roles' => $before], ['roles' => $after], ['role' => $roleModel->name], RoleChangeSeverity::for($added, []));
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
