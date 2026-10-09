<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Actions\RecordLastLogin;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('ends a lock exactly at locked_until', function (): void {
    Carbon::setTestNow('2030-01-01 12:00:00');
    $user = makeUser();
    $state = UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Locked, 'locked_until' => '2030-01-01 12:00:00']);

    expect($state->fresh()->canSignInNow())->toBeTrue();

    Carbon::setTestNow('2030-01-01 11:59:59');
    expect($state->fresh()->canSignInNow())->toBeFalse();
});

it('records the last login when the state row is missing and when it already exists', function (): void {
    $user = makeUser();

    app(RecordLastLogin::class)->handle($user, '10.0.0.1');
    $state = UserAccessState::where('user_id', $user->id)->sole();
    expect($state->status)->toBe(UserStatus::Active)->and($state->last_login_ip)->toBe('10.0.0.1');

    $state->update(['status' => UserStatus::Suspended, 'failed_login_count' => 3]);
    app(RecordLastLogin::class)->handle($user, '10.0.0.2');

    $state = UserAccessState::where('user_id', $user->id)->sole();
    expect($state->status)->toBe(UserStatus::Suspended)->and($state->failed_login_count)->toBe(0)->and($state->last_login_ip)->toBe('10.0.0.2');
});

it('survives a concurrent insert of the missing state row (insert or ignore, then lock)', function (): void {
    $user = makeUser();
    // The competing writer won the race: the row exists with its own values when our insert runs.
    DB::table('user_access_states')->insert(['user_id' => $user->id, 'status' => 'locked', 'locked_until' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);

    $locked = UserAccessState::lockedFor($user->id);

    expect($locked->status)->toBe(UserStatus::Locked)->and(UserAccessState::where('user_id', $user->id)->count())->toBe(1);
});
