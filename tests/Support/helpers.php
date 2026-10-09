<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Model;
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
    app(ActorContext::class)->runAs(null, fn () => $user->assignAccessRole('owner', confirmed: true));

    return $user;
}

function guardrailCode(Operation $operation): ?string
{
    try {
        app(Guardrails::class)->assert($operation);

        return null;
    } catch (AccessException $e) {
        expect($e->status)->toBe(403);

        return $e->errorCode;
    }
}

function makePermission(string $name, string $risk = 'low'): Permission
{
    return Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['risk' => $risk]);
}

/** @param  array<string, mixed>  $attributes */
function makeState(Model $user, string $status = 'active', array $attributes = []): UserAccessState
{
    return UserAccessState::create(['user_id' => $user->getKey(), 'status' => $status, ...$attributes]);
}

/**
 * A signed-in actor holding a role with the given permissions (all users read permissions by default).
 *
 * @param  list<string>|null  $permissions
 */
function usersActor(?array $permissions = null, string $email = 'actor@example.test'): TestUser
{
    $user = makeUser($email);
    $user->assignRole(makeRole('actor-'.uniqid(), $permissions ?? ['access.users.view']));

    return $user;
}
