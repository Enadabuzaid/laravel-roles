<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\OwnerGate;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Tests\Support\TestUser;

function makeUser(string $email = 'a@example.test'): TestUser
{
    return TestUser::create(['name' => 'A', 'email' => $email, 'password' => 'x']);
}

it('assigns, removes and syncs roles through actions', function () {
    $user = makeUser();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'viewer', 'guard_name' => 'web']);

    $user->assignAccessRole('admin');
    expect($user->hasRole('admin'))->toBeTrue();

    $user->syncAccessRoles(['viewer']);
    expect($user->getRoleNames()->all())->toBe(['viewer']);

    $user->removeAccessRole('viewer');
    expect($user->getRoleNames()->all())->toBe([]);
});

it('knows the owner', function () {
    $user = makeUser();
    Role::create(['name' => 'owner', 'guard_name' => 'web']);

    expect($user->isOwner())->toBeFalse();
    $user->assignAccessRole('owner');
    expect($user->fresh()->isOwner())->toBeTrue();
});

it('lets the owner pass every gate check', function () {
    $owner = makeUser('o@example.test');
    Role::create(['name' => 'owner', 'guard_name' => 'web']);
    $owner->assignAccessRole('owner');

    expect(Gate::forUser($owner->fresh())->allows('anything.at.all'))->toBeTrue();
});

it('lets non-owners fall through to normal permission checks', function () {
    $user = makeUser('u@example.test');
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Role::create(['name' => 'viewer', 'guard_name' => 'web'])->givePermissionTo('orders.view');
    $user->assignAccessRole('viewer');
    $user = $user->fresh();

    expect(Gate::forUser($user)->allows('orders.view'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('orders.refund'))->toBeFalse();
});

it('does not grant a role to a user whose assign fails inside the transaction', function () {
    $user = makeUser();

    expect(fn () => $user->assignAccessRole('does-not-exist'))->toThrow(RoleDoesNotExist::class);
    expect($user->getRoleNames()->all())->toBe([]);
});

it('does not block abilities defined on the gate for non-owners', function () {
    Gate::define('custom.ability', fn () => true);
    $user = makeUser('plain@example.test');

    expect(Gate::forUser($user)->allows('custom.ability'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('undefined.ability'))->toBeFalse();
});

it('returns exactly null for non-owners and true for owners from the owner gate', function () {
    $gate = new OwnerGate;
    $plain = makeUser('plain@example.test');
    $owner = makeUser('own@example.test');
    Role::create(['name' => 'owner', 'guard_name' => 'web']);
    $owner->assignAccessRole('owner');

    expect($gate($plain))->toBeNull()
        ->and($gate($owner->fresh()))->toBeTrue()
        ->and($gate(new stdClass))->toBeNull();
});

it('registers the owner gate as a Gate::before callback', function () {
    $callbacks = (new ReflectionProperty(app(GateContract::class), 'beforeCallbacks'))->getValue(app(GateContract::class));

    expect(collect($callbacks)->contains(fn ($callback) => $callback instanceof OwnerGate))->toBeTrue();
});
