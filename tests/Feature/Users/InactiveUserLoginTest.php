<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

it('refuses suspended users on every non-attempt login path and leaves them a guest', function (string $path) {
    $user = loginUser();
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);
    $guard = Auth::guard('web');

    $login = match ($path) {
        'login' => fn () => $guard->login($user),
        'login remember' => fn () => $guard->login($user, true),
        'loginUsingId' => fn () => $guard->loginUsingId($user->id),
        'Auth::login' => fn () => Auth::login($user),
    };

    try {
        $login();
        $this->fail('The login should have been refused.');
    } catch (ValidationException $e) {
        expect($e->errors()['email'])->toBe([trans('access::auth.inactive')]);
    }

    expect($guard->check())->toBeFalse();
})->with(['login', 'login remember', 'loginUsingId', 'Auth::login']);

it('refuses a remember-me recall of a suspended user', function () {
    $user = loginUser();
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);
    $guard = Auth::guard('web');
    $guard->setUser($user);

    expect(fn () => event(new Login('web', $user, true)))->toThrow(ValidationException::class);
    expect($guard->check())->toBeFalse();
});

it('lets active, state-less and expired-lock users through the login paths', function (?UserStatus $status, ?string $lockedUntil) {
    $user = loginUser();
    if ($status !== null) {
        UserAccessState::create(['user_id' => $user->id, 'status' => $status, 'locked_until' => $lockedUntil]);
    }

    Auth::guard('web')->login($user, true);
    expect(Auth::guard('web')->check())->toBeTrue();
    Auth::guard('web')->logout();
    expect(Auth::guard('web')->loginUsingId($user->id))->not->toBeFalse();
})->with([[null, null], [UserStatus::Active, null], [UserStatus::Locked, '2000-01-01 00:00:00']]);

it('does not double the refusal on a validated attempt', function () {
    $user = loginUser();
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);

    try {
        Auth::guard('web')->attempt(credentials($user));
    } catch (ValidationException $e) {
        expect($e->errors()['email'])->toHaveCount(1);
    }
});

it('fails open when the state table is missing', function () {
    $user = loginUser();
    Schema::drop('user_access_states');
    Route::middleware(['web', 'access.active'])->get('/_probe', fn () => response('ok'));

    expect(Auth::guard('web')->attempt(credentials($user)))->toBeTrue();
    Auth::guard('web')->logout();
    expect(Auth::guard('web')->login($user))->toBeNull();

    $this->actingAs($user)->get('/_probe')->assertOk();
    $this->actingAs($user)->getJson('/api/access/me')->assertForbidden()->assertJson(['code' => 'forbidden']);
});

it('fails open when the users module is disabled', function () {
    $user = loginUser();
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);
    config(['roles.modules.users' => false]);

    expect(Auth::guard('web')->attempt(credentials($user)))->toBeTrue();
});
