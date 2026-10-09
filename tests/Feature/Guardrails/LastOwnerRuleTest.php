<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;

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

it('lets the sole owner receive or lose a non-owner role', function () {
    $owner = makeOwner();
    $viewer = makeRole('viewer');

    expect(guardrailCode(new Operation(T::AssignRole, target: $owner, added: [$viewer])))->toBeNull()
        ->and(guardrailCode(new Operation(T::RemoveRole, target: $owner, removed: [$viewer])))->toBeNull();
});

it('does not count an inactive second owner towards the minimum', function (UserStatus $status) {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    UserAccessState::create(['user_id' => $second->id, 'status' => $status]);
    $role = Role::where('name', 'owner')->first();

    expect(guardrailCode(new Operation(T::RemoveRole, target: $owner, removed: [$role])))->toBe('guardrail.last_owner')
        ->and(guardrailCode(new Operation(T::SuspendUser, target: $owner)))->toBe('guardrail.last_owner')
        ->and(guardrailCode(new Operation(T::DeactivateUser, target: $owner)))->toBe('guardrail.last_owner')
        ->and(guardrailCode(new Operation(T::DeleteUser, target: $owner, confirmation: $owner->email)))->toBe('guardrail.last_owner');
})->with([UserStatus::Suspended, UserStatus::Deactivated, UserStatus::Invited, UserStatus::PendingVerification]);

it('lets an already inactive owner be suspended, deactivated, deleted or lose the role without changing the count', function (T $type) {
    makeOwner();
    $inactive = makeOwner('inactive@example.test');
    UserAccessState::create(['user_id' => $inactive->id, 'status' => UserStatus::Suspended]);
    $role = Role::where('name', 'owner')->first();

    expect(guardrailCode(new Operation($type, target: $inactive, confirmation: $inactive->email)))->toBeNull()
        ->and(guardrailCode(new Operation(T::RemoveRole, target: $inactive, removed: [$role])))->toBeNull();
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('allows suspending one of two active owners and blocks the last active one', function () {
    $first = makeOwner();
    $second = makeOwner('second@example.test');

    expect(guardrailCode(new Operation(T::SuspendUser, target: $first)))->toBeNull();

    UserAccessState::create(['user_id' => $first->id, 'status' => UserStatus::Suspended]);

    expect(guardrailCode(new Operation(T::SuspendUser, target: $second)))->toBe('guardrail.last_owner');
});

it('treats an owner with an active state row like one without a row', function () {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    UserAccessState::create(['user_id' => $second->id, 'status' => UserStatus::Active]);

    expect(guardrailCode(new Operation(T::SuspendUser, target: $owner)))->toBeNull();
});

it('keeps protecting a locked sole owner', function (?string $lockedUntil, T $type) {
    $owner = makeOwner();
    UserAccessState::create(['user_id' => $owner->id, 'status' => UserStatus::Locked, 'locked_until' => $lockedUntil]);
    $role = Role::where('name', 'owner')->first();

    $operation = $type === T::RemoveRole
        ? new Operation($type, target: $owner, removed: [$role])
        : new Operation($type, target: $owner, confirmation: $owner->email);

    expect(guardrailCode($operation))->toBe('guardrail.last_owner');
})->with([[null], ['2099-01-01 00:00:00'], ['2000-01-01 00:00:00']])->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser, T::RemoveRole]);
