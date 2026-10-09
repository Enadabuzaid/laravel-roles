<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Spatie\Permission\PermissionRegistrar;

final class RestoreRole extends Action
{
    public function handle(Role $role): Role
    {
        app(ActorContext::class)->assertKnownActor();

        $this->transaction(function () use ($role): void {
            $fresh = Role::withTrashed()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();

            if (! $fresh->trashed()) {
                throw new AccessException('The role is not trashed.', 'role.not_trashed', 409);
            }

            $taken = Role::query()->where('name', $fresh->name)->where('guard_name', $fresh->guard_name)->whereKeyNot($fresh->getKey())->exists();
            if ($taken) {
                throw new AccessException('A role with this name already exists.', 'role.name_taken', 409);
            }

            $fresh->restore();
            app(AuditRecorder::class)->record('roles', 'restored', $fresh);
            $role->setRawAttributes($fresh->getAttributes(), true);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
