<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Support\Facades\DB;

it('counts zero owners without a super role', function () {
    expect(app(OwnerCounter::class)->count())->toBe(0);
});

it('counts owners without a state row as active', function () {
    makeOwner();
    makeOwner('second@example.test');

    expect(app(OwnerCounter::class)->count())->toBe(2);
});

it('counts only active owners', function (UserStatus $status) {
    makeOwner();
    $inactive = makeOwner('second@example.test');
    UserAccessState::create(['user_id' => $inactive->id, 'status' => $status]);

    expect(app(OwnerCounter::class)->count())->toBe(1);
})->with([UserStatus::Suspended, UserStatus::Deactivated, UserStatus::Invited, UserStatus::PendingVerification]);

it('counts an owner with an active state row', function () {
    $owner = makeOwner();
    UserAccessState::create(['user_id' => $owner->id, 'status' => UserStatus::Active]);

    expect(app(OwnerCounter::class)->count())->toBe(1);
});

it('ignores state rows of non-owners', function () {
    makeOwner();
    $plain = makeUser('plain@example.test');
    UserAccessState::create(['user_id' => $plain->id, 'status' => UserStatus::Suspended]);

    expect(app(OwnerCounter::class)->count())->toBe(1);
});

it('keeps isOwner role-based and reports active owners separately', function () {
    $active = makeOwner();
    $suspended = makeOwner('second@example.test');
    $plain = makeUser('plain@example.test');
    UserAccessState::create(['user_id' => $suspended->id, 'status' => UserStatus::Suspended]);
    $counter = app(OwnerCounter::class);

    expect($counter->isOwner($suspended))->toBeTrue()->and($counter->isActiveOwner($suspended))->toBeFalse()
        ->and($counter->isOwner($active))->toBeTrue()->and($counter->isActiveOwner($active))->toBeTrue()
        ->and($counter->isOwner($plain))->toBeFalse()->and($counter->isActiveOwner($plain))->toBeFalse();
});

it('counts active owners with one query', function () {
    makeOwner();
    $suspended = makeOwner('second@example.test');
    UserAccessState::create(['user_id' => $suspended->id, 'status' => UserStatus::Suspended]);
    $counter = app(OwnerCounter::class);
    $counter->count();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $counter->count();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // One for the super role lookup and one for the active owner count.
    expect($queries)->toBe(2);
});

it('counts a locked owner as active whether or not the lock expired', function (?string $lockedUntil) {
    $owner = makeOwner();
    UserAccessState::create(['user_id' => $owner->id, 'status' => UserStatus::Locked, 'locked_until' => $lockedUntil]);
    $counter = app(OwnerCounter::class);

    expect($counter->count())->toBe(1)->and($counter->isActiveOwner($owner))->toBeTrue();
})->with([null, '2099-01-01 00:00:00', '2000-01-01 00:00:00']);
