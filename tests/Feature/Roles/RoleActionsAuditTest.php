<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Support\Facades\DB;

function highRiskRole(string $name = 'refunds'): void
{
    Permission::create(['name' => "{$name}.do", 'guard_name' => 'web', 'risk' => 'high']);
    makeRole($name, ["{$name}.do"]);
}

it('audits an assignment once with actor, subject and role lists', function () {
    $owner = makeOwner('boss@example.test');
    ActionLog::query()->delete();
    $target = makeUser('t@example.test');
    makeRole('viewer');

    app(ActorContext::class)->runAs($owner, fn () => $target->assignAccessRole('viewer'));

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.assigned')->and($log->module)->toBe('roles')->and($log->severity)->toBe('info')
        ->and($log->subject_id)->toBe($target->id)->and($log->actor_id)->toBe($owner->id)
        ->and($log->old_values)->toEqual(['roles' => []])->and($log->new_values)->toEqual(['roles' => ['viewer']])
        ->and($log->meta)->toEqual(['role' => 'viewer']);
});

it('audits a removal once', function () {
    makeOwner();
    $target = makeUser('t@example.test');
    makeRole('viewer');
    $target->assignAccessRole('viewer');
    ActionLog::query()->delete();

    $target->removeAccessRole('viewer');

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.removed')->and($log->actor_type)->toBeNull()
        ->and($log->old_values)->toEqual(['roles' => ['viewer']])->and($log->new_values)->toEqual(['roles' => []])
        ->and($log->meta)->toEqual(['role' => 'viewer']);
});

it('audits a sync once with added and removed lists', function () {
    $target = makeUser('t@example.test');
    makeRole('a');
    makeRole('b');
    $target->assignAccessRole('a');
    ActionLog::query()->delete();

    $target->syncAccessRoles(['b']);

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.synced')->and($log->old_values)->toEqual(['roles' => ['a']])
        ->and($log->new_values)->toEqual(['roles' => ['b']])->and($log->meta)->toEqual(['added' => ['b'], 'removed' => ['a']]);
});

it('uses critical severity for the owner role and warning for a confirmed high-risk role', function () {
    $user = makeUser('t@example.test');
    makeRole('owner', [], ['is_system' => true]);
    highRiskRole();

    $user->assignAccessRole('owner', confirmed: true);
    expect(ActionLog::sole()->severity)->toBe('critical');

    ActionLog::query()->delete();
    $other = makeUser('o@example.test');
    $other->assignAccessRole('refunds', confirmed: true);
    expect(ActionLog::sole()->severity)->toBe('warning');
});

it('writes no entry and changes nothing on a violation', function () {
    $owner = makeOwner();
    ActionLog::query()->delete();

    try {
        $owner->removeAccessRole('owner');
        test()->fail('expected violation');
    } catch (AccessException) {
    }

    expect(ActionLog::count())->toBe(0)->and($owner->fresh()->isOwner())->toBeTrue();
});

it('writes no entry when the user already holds the role', function () {
    $user = makeUser();
    makeRole('viewer');
    $user->assignAccessRole('viewer');
    ActionLog::query()->delete();

    $user->assignAccessRole('viewer');
    $user->syncAccessRoles(['viewer']);

    expect(ActionLog::count())->toBe(0);
});

it('writes no entry when an outer transaction rolls back', function () {
    $user = makeUser();
    makeRole('viewer');

    try {
        DB::transaction(function () use ($user) {
            $user->assignAccessRole('viewer');
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(ActionLog::count())->toBe(0)->and($user->fresh()->getRoleNames()->all())->toBe([]);
});

it('still changes the role when the audit store is unavailable', function () {
    $user = makeUser();
    makeRole('viewer');
    // A thrown write failure (a dropped table would abort the surrounding test transaction on pgsql).
    ActionLog::creating(fn () => throw new RuntimeException('audit store unavailable'));

    $user->assignAccessRole('viewer');

    expect($user->fresh()->hasRole('viewer'))->toBeTrue();
});

it('records a system-actor entry when no actor exists (console make:admin path)', function () {
    $user = makeUser();
    makeRole('owner', [], ['is_system' => true]);

    $user->assignAccessRole('owner', confirmed: true);

    $log = ActionLog::sole();
    expect($user->fresh()->isOwner())->toBeTrue()->and($log->actor_type)->toBeNull()->and($log->actor_id)->toBeNull();
});
