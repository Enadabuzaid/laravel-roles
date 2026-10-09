<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfDestructiveRule;
use Enadstack\LaravelRoles\Guardrails\Rules\TargetAboveActorRule;
use Tests\Support\TestUser;

/** @param list<string> $permissions */
function userWith(string $email, array $permissions): TestUser
{
    $user = makeUser($email);
    $user->assignRole(makeRole('r-'.uniqid(), $permissions));

    return $user;
}

it('blocks a non-owner managing an owner', function (T $type) {
    $owner = makeOwner();
    makeOwner('second@example.test');
    $actor = userWith('actor@example.test', ['reports.view']);

    expect(guardrailCode(new Operation($type, actor: $actor, target: $owner, confirmation: $owner->email)))->toBe('guardrail.target_above_actor');
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('blocks a target with a permission the actor lacks', function (T $type) {
    makeOwner();
    $actor = userWith('actor@example.test', ['reports.view']);
    $target = userWith('target@example.test', ['reports.view', 'billing.view']);

    expect(guardrailCode(new Operation($type, actor: $actor, target: $target, confirmation: $target->email)))->toBe('guardrail.target_above_actor');
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('allows equal permissions, fewer permissions and no permissions', function (T $type) {
    makeOwner();
    $actor = userWith('actor@example.test', ['reports.view', 'billing.view']);
    $equal = userWith('equal@example.test', ['reports.view', 'billing.view']);
    $fewer = userWith('fewer@example.test', ['reports.view']);
    $none = makeUser('none@example.test');

    foreach ([$equal, $fewer, $none] as $target) {
        expect(guardrailCode(new Operation($type, actor: $actor, target: $target, confirmation: $target->email)))->toBeNull();
    }
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('counts direct permissions of the target and the actor', function () {
    makeOwner();
    $actor = userWith('actor@example.test', ['reports.view']);
    $target = makeUser('target@example.test');
    $target->givePermissionTo(makePermission('billing.view'));

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $actor, target: $target)))->toBe('guardrail.target_above_actor');

    $actor->givePermissionTo('billing.view');

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $actor->fresh(), target: $target)))->toBeNull();
});

it('skips owner actors and the system actor', function (T $type) {
    $owner = makeOwner();
    $second = makeOwner('second@example.test');
    $target = userWith('target@example.test', ['billing.view']);

    expect(guardrailCode(new Operation($type, actor: $owner, target: $second, confirmation: $second->email)))->toBeNull()
        ->and(guardrailCode(new Operation($type, actor: $owner, target: $target, confirmation: $target->email)))->toBeNull()
        ->and(guardrailCode(new Operation($type, actor: null, target: $target, confirmation: $target->email)))->toBeNull();
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('can be switched off', function () {
    $owner = makeOwner();
    makeOwner('second@example.test');
    $actor = userWith('actor@example.test', ['reports.view']);

    config(['roles.guardrails.prevent_target_above_actor' => false]);

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $actor, target: $owner)))->toBeNull();
});

it('does not apply to role operations', function () {
    $owner = makeOwner();
    $actor = userWith('actor@example.test', ['reports.view']);

    expect(guardrailCode(new Operation(T::AssignRole, actor: $actor, target: $owner, added: [])))->toBeNull();
});

it('is listed right after the self destructive rule', function () {
    $rules = config('roles.guardrails.rules');
    $position = array_search(SelfDestructiveRule::class, $rules, true);

    expect($rules[$position + 1])->toBe(TargetAboveActorRule::class);
});
