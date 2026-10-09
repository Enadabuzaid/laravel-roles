<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Models\Role;

it('blocks removing the owner role from the sole owner', function () {
    $owner = makeOwner();
    $role = Role::where('name', 'owner')->first();

    expect(guardrailCode(new Operation(T::RemoveRole, target: $owner, removed: [$role])))->toBe('guardrail.last_owner');
});

it('allows removing an owner when two owners exist', function () {
    $owner = makeOwner();
    makeOwner('second@example.test');
    $role = Role::where('name', 'owner')->first();

    expect(guardrailCode(new Operation(T::RemoveRole, target: $owner, removed: [$role])))->toBeNull();
});

it('honours min_owners', function () {
    config(['roles.guardrails.min_owners' => 2]);
    $owner = makeOwner();
    makeOwner('second@example.test');
    $role = Role::where('name', 'owner')->first();

    expect(guardrailCode(new Operation(T::RemoveRole, target: $owner, removed: [$role])))->toBe('guardrail.last_owner');
});

it('blocks a sync that drops the owner role from the sole owner', function () {
    $owner = makeOwner();
    $role = Role::where('name', 'owner')->first();
    $other = makeRole('viewer');

    expect(guardrailCode(new Operation(T::SyncRoles, target: $owner, added: [$other], removed: [$role])))->toBe('guardrail.last_owner');
});

it('blocks suspending, deactivating and deleting the sole owner', function (T $type) {
    $owner = makeOwner();

    expect(guardrailCode(new Operation($type, target: $owner, confirmation: $owner->email)))->toBe('guardrail.last_owner');
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('allows the same operations on a non-owner', function (T $type) {
    makeOwner();
    $user = makeUser('plain@example.test');

    expect(guardrailCode(new Operation($type, target: $user, confirmation: $user->email)))->toBeNull();
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('applies to the system actor too', function () {
    $owner = makeOwner();

    expect(guardrailCode(new Operation(T::SuspendUser, actor: null, target: $owner)))->toBe('guardrail.last_owner');
});
