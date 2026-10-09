<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class RemoveRoleFromUser extends Action
{
    public function handle(AccessUser&Model $user, string|Role $role): void
    {
        $this->transaction(fn () => $user->removeRole($role));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
