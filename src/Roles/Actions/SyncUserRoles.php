<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Actions;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\Action;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class SyncUserRoles extends Action
{
    /**
     * @param  list<string|Role>  $roles
     */
    public function handle(AccessUser&Model $user, array $roles): void
    {
        $this->transaction(fn () => $user->syncRoles($roles));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
