<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestUser;

function loginUser(string $email = 'login@example.test'): TestUser
{
    return TestUser::create(['name' => 'L', 'email' => $email, 'password' => Hash::make('secret-pass')]);
}

/** @return array<string, string> */
function credentials(TestUser $user): array
{
    return ['email' => $user->email, 'password' => 'secret-pass'];
}

it('blocks sign in for every non-active status through a real attempt', function (UserStatus $status, ?string $lockedUntil) {
    $user = loginUser();
    UserAccessState::create(['user_id' => $user->id, 'status' => $status, 'locked_until' => $lockedUntil]);

    try {
        Auth::guard('web')->attempt(credentials($user));
        $this->fail('The attempt should have been refused.');
    } catch (ValidationException $e) {
        expect($e->errors()['email'][0])->toBe(trans('access::auth.inactive'))->and($e->errors()['email'][0])->not->toBe('access::auth.inactive');
    }

    expect(Auth::guard('web')->check())->toBeFalse();
})->with([
    'suspended' => [UserStatus::Suspended, null],
    'deactivated' => [UserStatus::Deactivated, null],
    'invited' => [UserStatus::Invited, null],
    'pending verification' => [UserStatus::PendingVerification, null],
    'locked with no end' => [UserStatus::Locked, null],
    'locked until the future' => [UserStatus::Locked, '2099-01-01 00:00:00'],
]);

it('lets active users, users without a state and expired locks sign in', function (?UserStatus $status, ?string $lockedUntil) {
    $user = loginUser();
    if ($status !== null) {
        UserAccessState::create(['user_id' => $user->id, 'status' => $status, 'locked_until' => $lockedUntil]);
    }

    expect(Auth::guard('web')->attempt(credentials($user)))->toBeTrue()->and(Auth::guard('web')->check())->toBeTrue();
})->with([
    'no state row' => [null, null],
    'active' => [UserStatus::Active, null],
    'lock expired' => [UserStatus::Locked, '2000-01-01 00:00:00'],
]);

it('does not reveal anything for wrong credentials of a suspended user', function () {
    $user = loginUser();
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);

    expect(Auth::guard('web')->attempt(['email' => $user->email, 'password' => 'wrong']))->toBeFalse();
});

it('has an arabic translation', function () {
    app()->setLocale('ar');

    expect(trans('access::auth.inactive'))->not->toBe('access::auth.inactive')->not->toBe(trans('access::auth.inactive', [], 'en'));
});
