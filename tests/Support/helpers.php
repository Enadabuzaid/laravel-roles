<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ActorContext;
use Tests\Support\TestUser;

function makeUser(string $email = 'a@example.test'): TestUser
{
    return TestUser::create(['name' => 'A', 'email' => $email, 'password' => 'x']);
}

/**
 * @param  list<string>  $permissions
 * @param  array<string, mixed>  $attributes
 */
function makeRole(string $name, array $permissions = [], array $attributes = []): Role
{
    $role = Role::create(['name' => $name, 'guard_name' => 'web', ...$attributes]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    if ($permissions !== []) {
        $role->givePermissionTo($permissions);
    }

    return $role;
}

function makeOwner(string $email = 'owner@example.test'): TestUser
{
    if (! Role::where('name', 'owner')->where('guard_name', 'web')->exists()) {
        makeRole('owner', [], ['is_system' => true]);
    }

    $user = makeUser($email);
    app(ActorContext::class)->runAs(null, fn () => $user->assignAccessRole('owner'));

    return $user;
}
