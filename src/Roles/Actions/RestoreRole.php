<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Spatie\Permission\PermissionRegistrar;

final class RestoreRole extends Action
{
    public function handle(Role $role, bool $confirmed = false): Role
    {
        app(ActorContext::class)->assertKnownActor();

        $this->transaction(function () use ($role, $confirmed): void {
            app(OwnerCounter::class)->lockSuperRole();
            $fresh = Role::withTrashed()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            if (! $fresh->trashed()) {
                throw new AccessException('The role is not trashed.', 'role.not_trashed', 409);
            }

            // Defensive: the unique (name, guard_name) index covers trashed rows, so this is unreachable on a consistent schema.
            $taken = Role::query()->where('name', $fresh->name)->where('guard_name', $fresh->guard_name)->whereKeyNot($fresh->getKey())->exists();
            if ($taken) {
                throw new AccessException('A role with this name already exists.', 'role.name_taken', 409);
            }

            app(Guardrails::class)->assert(new Operation(
                OperationType::RestoreRole,
                actor: app(ActorContext::class)->actor(),
                role: $fresh,
                confirmed: $confirmed,
            ));

            $fresh->restore();
            app(AuditRecorder::class)->record('roles', 'restored', $fresh);
            $role->setRawAttributes($fresh->getAttributes(), true);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
