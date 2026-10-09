<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Roles\Actions\AssignRoleToUser;
use Enadstack\LaravelRoles\Roles\Actions\RemoveRoleFromUser;
use Enadstack\LaravelRoles\Roles\Actions\SyncUserRoles;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Tests\Support\TestUser;

function expectViolation(Closure $callback, string $code): void
{
    try {
        $callback();
        test()->fail("Expected guardrail {$code}");
    } catch (AccessException $e) {
        expect($e->errorCode)->toBe($code);
    }
}

it('blocks removing the last owner but allows it when another owner exists', function () {
    $owner = makeOwner('o1@example.test');

    expectViolation(fn () => $owner->removeAccessRole('owner'), 'guardrail.last_owner');
    expect($owner->fresh()->isOwner())->toBeTrue();

    makeOwner('o2@example.test');
    $owner->removeAccessRole('owner');
    expect($owner->fresh()->isOwner())->toBeFalse();
});

it('blocks changing your own role', function () {
    makeOwner('boss@example.test');
    $admin = makeUser('admin@example.test');
    makeRole('manager');

    expectViolation(fn () => app(ActorContext::class)->runAs($admin, fn () => $admin->assignAccessRole('manager')), 'guardrail.self_role_change');
    expect($admin->fresh()->getRoleNames()->all())->toBe([]);
});

it('stops a non-owner from granting more than they hold', function () {
    $actor = makeUser('actor@example.test');
    makeRole('support', ['tickets.view'])->users()->attach($actor);
    $target = makeUser('target@example.test');
    makeRole('finance', ['tickets.view', 'invoices.view']);

    expectViolation(fn () => app(ActorContext::class)->runAs($actor->fresh(), fn () => $target->assignAccessRole('finance')), 'guardrail.escalation');
});

it('requires explicit confirmation for high-risk roles', function () {
    Permission::create(['name' => 'orders.refund', 'guard_name' => 'web', 'risk' => 'high']);
    makeRole('refunds', ['orders.refund']);
    $target = makeUser();

    expectViolation(fn () => $target->assignAccessRole('refunds'), 'guardrail.high_risk_confirm_required');
    expect($target->getRoleNames()->all())->toBe([]);

    $target->assignAccessRole('refunds', confirmed: true);
    expect($target->fresh()->hasRole('refunds'))->toBeTrue();
});

it('lets system actors (console) assign roles without actor-based rules', function () {
    $user = makeUser();
    makeRole('viewer');

    $user->assignAccessRole('viewer');

    expect($user->fresh()->hasRole('viewer'))->toBeTrue();
});

it('blocks a sync that drops the sole owner', function () {
    $owner = makeOwner();
    makeRole('viewer');

    expectViolation(fn () => $owner->syncAccessRoles(['viewer']), 'guardrail.last_owner');
    expect($owner->fresh()->getRoleNames()->all())->toBe(['owner']);
});

it('leaves the user unchanged when a sync guardrail fails', function () {
    makeOwner();
    $target = makeUser('t@example.test');
    makeRole('viewer');
    Permission::create(['name' => 'orders.refund', 'guard_name' => 'web', 'risk' => 'high']);
    makeRole('refunds', ['orders.refund']);
    $target->assignAccessRole('viewer');

    expectViolation(fn () => $target->syncAccessRoles(['refunds']), 'guardrail.high_risk_confirm_required');

    expect($target->fresh()->getRoleNames()->all())->toBe(['viewer']);
});

it('treats assigning an already-held role as a silent no-op', function () {
    $user = makeUser();
    makeRole('viewer');
    $user->assignAccessRole('viewer');

    $user->assignAccessRole('viewer');

    expect($user->fresh()->getRoleNames()->all())->toBe(['viewer']);
});

it('does not require confirmation again for an already-held high-risk role', function () {
    Permission::create(['name' => 'orders.refund', 'guard_name' => 'web', 'risk' => 'high']);
    makeRole('refunds', ['orders.refund']);
    $user = makeUser();
    $user->assignAccessRole('refunds', confirmed: true);

    $user->assignAccessRole('refunds');

    expect($user->fresh()->hasRole('refunds'))->toBeTrue();
});

it('still refuses an unknown role by name', function () {
    expect(fn () => makeUser()->assignAccessRole('ghost'))->toThrow(RoleDoesNotExist::class);
});

it('allows assigning and removing a non-owner role for the sole owner', function () {
    $owner = makeOwner();
    makeRole('viewer');

    $owner->assignAccessRole('viewer');
    expect($owner->fresh()->getRoleNames()->sort()->values()->all())->toBe(['owner', 'viewer']);

    $owner->removeAccessRole('viewer');
    expect($owner->fresh()->getRoleNames()->all())->toBe(['owner']);
});

it('locks the super role row before the guardrail queries on postgres', function (string $operation) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('FOR UPDATE row locking is asserted on pgsql only; sqlite has no row locks.');
    }

    $owner = makeOwner();
    makeOwner('second@example.test');
    $viewer = makeRole('viewer');
    $target = makeUser('t@example.test');
    $target->assignAccessRole($viewer);

    DB::flushQueryLog();
    DB::enableQueryLog();
    match ($operation) {
        'assign' => app(AssignRoleToUser::class)->handle($owner, $viewer),
        'remove' => app(RemoveRoleFromUser::class)->handle($target, 'viewer'),
        'sync' => app(SyncUserRoles::class)->handle($target, []),
    };
    $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $q): string => strtolower($q))->values();
    DB::disableQueryLog();

    $lock = $queries->search(fn (string $q): bool => str_contains($q, 'for update'));
    $firstMembershipRead = $queries->search(fn (string $q): bool => str_contains($q, 'model_has_roles'));

    expect($lock)->not->toBeFalse()->and($firstMembershipRead)->not->toBeFalse()->and($lock)->toBeLessThan($firstMembershipRead);
})->with(['assign', 'remove', 'sync']);

it('uses the real DB state when the loaded roles relation is stale (user is actually the sole owner)', function (string $operation) {
    $owner = makeOwner();
    makeRole('viewer');
    $stale = TestUser::find($owner->id);
    $stale->roles()->detach();
    $stale->unsetRelation('roles');
    $stale->load('roles'); // loaded: no roles
    TestUser::find($owner->id)->assignRole('owner'); // DB behind the model's back: now the sole owner

    expectViolation(fn () => match ($operation) {
        'remove' => $stale->removeAccessRole('owner'),
        'sync' => $stale->syncAccessRoles(['viewer']),
    }, 'guardrail.last_owner');

    expect(TestUser::find($owner->id)->isOwner())->toBeTrue();
})->with(['remove', 'sync']);

it('does not raise a false violation when the loaded relation wrongly says the user is the sole owner', function (string $operation) {
    $user = makeOwner();
    makeRole('viewer');
    $user->load('roles'); // loaded: owner
    TestUser::find($user->id)->removeRole('owner'); // DB: no longer an owner

    match ($operation) {
        'remove' => $user->removeAccessRole('owner'),
        'sync' => $user->syncAccessRoles(['viewer']),
    };

    expect(TestUser::find($user->id)->getRoleNames()->all())->toBe($operation === 'sync' ? ['viewer'] : []);
})->with(['remove', 'sync']);

it('behaves the same without row locks on sqlite', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('sqlite behavior check.');
    }

    $owner = makeOwner();
    expectViolation(fn () => $owner->removeAccessRole('owner'), 'guardrail.last_owner');
    makeOwner('second@example.test');
    $owner->removeAccessRole('owner');

    expect($owner->fresh()->isOwner())->toBeFalse();
});

it('does not fail role removal when the super role has not been created yet', function () {
    $user = makeUser();
    $role = makeRole('viewer');
    $user->assignAccessRole($role);

    $user->removeAccessRole($role);

    expect($user->fresh()->getRoleNames()->all())->toBe([]);
});
