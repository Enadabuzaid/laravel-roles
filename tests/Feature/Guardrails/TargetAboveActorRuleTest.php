<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfDestructiveRule;
use Enadstack\LaravelRoles\Guardrails\Rules\TargetAboveActorRule;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
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

it('does not let a suspended owner with a surviving session skip the rule', function () {
    $owner = makeOwner();
    $other = makeOwner('other@example.test');
    $target = userWith('target@example.test', ['billing.view']);
    UserAccessState::create(['user_id' => $owner->id, 'status' => UserStatus::Suspended]);

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $owner, target: $target)))->toBe('guardrail.target_above_actor')
        ->and(guardrailCode(new Operation(T::SuspendUser, actor: $owner, target: $other)))->toBe('guardrail.target_above_actor');
});

it('keeps a locked owner as an owner actor', function () {
    $owner = makeOwner();
    makeOwner('other@example.test');
    $target = userWith('target@example.test', ['billing.view']);
    UserAccessState::create(['user_id' => $owner->id, 'status' => UserStatus::Locked]);

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $owner, target: $target)))->toBeNull();
});

it('ignores deprecated permissions of the target', function () {
    makeOwner();
    $actor = userWith('actor@example.test', ['reports.view']);
    $target = userWith('target@example.test', ['reports.view', 'old.thing']);

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $actor, target: $target)))->toBe('guardrail.target_above_actor');

    Permission::where('name', 'old.thing')->update(['deprecated_at' => now()]);

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $actor, target: $target)))->toBeNull();
});

it('ignores permissions of another guard', function () {
    makeOwner();
    $actor = userWith('actor@example.test', ['reports.view']);
    $target = userWith('target@example.test', ['reports.view']);
    $foreign = Permission::create(['name' => 'foreign.thing', 'guard_name' => 'api']);
    $target->permissions()->attach($foreign);

    expect(guardrailCode(new Operation(T::SuspendUser, actor: $actor, target: $target)))->toBeNull();
});

it('blocks removing or shrinking the roles of a more powerful user but not assigning', function (): void {
    makeOwner();
    $actor = userWith('actor@example.test', ['reports.view']);
    $target = userWith('target@example.test', ['reports.view', 'billing.view']);
    $role = $target->roles->first();

    expect(guardrailCode(new Operation(T::RemoveRole, actor: $actor, target: $target, removed: [$role])))->toBe('guardrail.target_above_actor')
        ->and(guardrailCode(new Operation(T::SyncRoles, actor: $actor, target: $target, removed: [$role])))->toBe('guardrail.target_above_actor')
        ->and(guardrailCode(new Operation(T::SyncRoles, actor: $actor, target: $target, added: [makeRole('plain')])))->toBeNull()
        ->and(guardrailCode(new Operation(T::AssignRole, actor: $actor, target: $target, added: [makeRole('plain-2')])))->toBeNull();
});

it('allows removing roles from equal or lesser users, and owners are exempt', function (): void {
    $owner = makeOwner();
    $actor = userWith('actor@example.test', ['reports.view']);
    $peer = userWith('peer@example.test', ['reports.view']);
    $lesser = userWith('lesser@example.test', []);
    $strong = userWith('strong@example.test', ['reports.view', 'billing.view']);

    expect(guardrailCode(new Operation(T::RemoveRole, actor: $actor, target: $peer, removed: [$peer->roles->first()])))->toBeNull()
        ->and(guardrailCode(new Operation(T::RemoveRole, actor: $actor, target: $lesser, removed: [$lesser->roles->first()])))->toBeNull()
        ->and(guardrailCode(new Operation(T::SyncRoles, actor: $owner, target: $strong, removed: [$strong->roles->first()])))->toBeNull();
});

it('refuses through HTTP: an assigner cannot empty a more powerful user', function (): void {
    makeOwner();
    $actor = usersActor(['access.roles.assign', 'access.users.view', 'reports.view'], 'assigner@example.test');
    $target = userWith('target@example.test', ['reports.view', 'billing.view']);
    $role = $target->roles->first();

    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}/roles/{$role->id}")->assertForbidden()->assertJsonPath('code', 'guardrail.target_above_actor');
    $this->actingAs($actor)->putJson("/api/access/users/{$target->id}/roles", ['roles' => []])->assertForbidden()->assertJsonPath('code', 'guardrail.target_above_actor');
    expect($target->fresh()->roles)->toHaveCount(1);
});
