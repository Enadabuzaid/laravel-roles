<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestUser;

it('casts the state row and relates it to the configured user model', function (): void {
    $user = makeUser();
    $state = UserAccessState::create(['user_id' => $user->id, 'status' => 'suspended', 'locked_until' => now()->addHour(), 'last_login_at' => now()]);
    $state->refresh();

    expect($state->status)->toBe(UserStatus::Suspended)
        ->and($state->locked_until)->toBeInstanceOf(CarbonInterface::class)
        ->and($state->failed_login_count)->toBe(0)
        ->and($state->user)->toBeInstanceOf(TestUser::class)
        ->and($state->user->is($user))->toBeTrue()
        ->and($user->accessState->is($state))->toBeTrue();
});

it('works with a host user table that has none of the optional columns', function (): void {
    expect(Schema::hasColumn('users', 'two_factor_confirmed_at'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'deleted_at'))->toBeFalse();

    $user = makeUser();

    expect($user->accessStatus())->toBe(UserStatus::Active)->and($user->isAccessActive())->toBeTrue();
});

it('defaults accessStatus to Active without a row and reads the row otherwise', function (): void {
    $user = makeUser();
    expect($user->accessStatus())->toBe(UserStatus::Active);

    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);

    expect($user->fresh()->accessStatus())->toBe(UserStatus::Suspended)
        ->and($user->fresh()->isAccessActive())->toBeFalse();
});

it('honours the configured default status when there is no row', function (): void {
    config(['roles.users.default_status' => 'invited']);

    expect(makeUser()->accessStatus())->toBe(UserStatus::Invited);
});

it('has the sign in truth table', function (UserStatus $status, ?string $lockedUntil, bool $statusOnly, bool $now): void {
    expect($status->canSignIn())->toBe($statusOnly);

    $state = new UserAccessState(['status' => $status, 'locked_until' => $lockedUntil !== null ? now()->modify($lockedUntil) : null]);

    expect($state->canSignInNow())->toBe($now);
})->with([
    'active' => [UserStatus::Active, null, true, true],
    'invited' => [UserStatus::Invited, null, false, false],
    'pending verification' => [UserStatus::PendingVerification, null, false, false],
    'suspended' => [UserStatus::Suspended, null, false, false],
    'deactivated' => [UserStatus::Deactivated, null, false, false],
    'locked future' => [UserStatus::Locked, '+1 hour', false, false],
    'locked past' => [UserStatus::Locked, '-1 minute', false, true],
    'locked without date' => [UserStatus::Locked, null, false, false],
    'suspended with past lock' => [UserStatus::Suspended, '-1 hour', false, false],
]);

it('marks only deactivated as final', function (): void {
    expect(array_values(array_filter(UserStatus::cases(), fn (UserStatus $s): bool => $s->isFinal())))->toBe([UserStatus::Deactivated]);
});

it('scopes invitations by lifecycle and reports usability', function (): void {
    $make = fn (string $email, array $attributes) => AccessInvitation::create([
        'email' => $email, 'token_hash' => hash('sha256', $email), 'expires_at' => now()->addDay(), ...$attributes,
    ]);

    $pending = $make('p@example.test', []);
    $accepted = $make('a@example.test', ['accepted_at' => now()]);
    $revoked = $make('r@example.test', ['revoked_at' => now()]);
    $expired = $make('e@example.test', ['expires_at' => now()->subMinute()]);

    expect(AccessInvitation::pending()->pluck('id')->all())->toBe([$pending->id])
        ->and(AccessInvitation::accepted()->pluck('id')->all())->toBe([$accepted->id])
        ->and(AccessInvitation::revoked()->pluck('id')->all())->toBe([$revoked->id])
        ->and(AccessInvitation::expired()->pluck('id')->all())->toBe([$expired->id])
        ->and($pending->isUsable())->toBeTrue()
        ->and($accepted->isUsable())->toBeFalse()
        ->and($revoked->isUsable())->toBeFalse()
        ->and($expired->isUsable())->toBeFalse()
        ->and($pending->toArray())->not->toHaveKey('token_hash');
});

it('casts saved view filters to an array and keeps names unique per owner and page', function (): void {
    $view = SavedView::create(['user_id' => 1, 'page' => 'users', 'name' => 'Admins', 'filters' => ['role' => 'admin', 'status' => ['active']]]);

    expect($view->fresh()->filters)->toEqual(['role' => 'admin', 'status' => ['active']]);

    SavedView::create(['user_id' => 2, 'page' => 'users', 'name' => 'Admins', 'filters' => []]);
    // A savepoint keeps the pgsql test transaction usable after the expected constraint violation.
    expect(fn () => DB::transaction(fn () => SavedView::create(['user_id' => 1, 'page' => 'users', 'name' => 'Admins', 'filters' => []])))
        ->toThrow(QueryException::class);
});

it('keeps the new tables free of foreign keys', function (): void {
    expect(Schema::getForeignKeys('user_access_states'))->toBe([])
        ->and(Schema::getForeignKeys('access_invitations'))->toBe([])
        ->and(Schema::getForeignKeys('access_saved_views'))->toBe([]);
});
