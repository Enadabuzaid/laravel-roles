<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', 'access.active'])->get('/_probe', fn () => response('ok'));
});

it('passes active users and users without a state', function (): void {
    $plain = makeUser('plain@example.test');
    $active = makeUser('active@example.test');
    UserAccessState::create(['user_id' => $active->id, 'status' => UserStatus::Active]);

    $this->actingAs($plain)->get('/_probe')->assertOk();
    $this->actingAs($active)->getJson('/_probe')->assertOk();
});

it('passes guests through', function (): void {
    $this->get('/_probe')->assertOk();
});

it('signs a suspended user out mid-session and redirects web requests to login', function (): void {
    Route::get('/login', fn () => 'login')->name('login');
    $user = makeUser('s@example.test');

    $this->actingAs($user)->get('/_probe')->assertOk();
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);

    $this->get('/_probe')->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('answers 403 for web requests when the host has no login route', function (): void {
    $user = makeUser('s@example.test');
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Deactivated]);

    $this->actingAs($user)->get('/_probe')->assertForbidden();
    $this->assertGuest();
});

it('answers 403 account_inactive in the envelope for JSON requests', function (): void {
    $user = makeUser('s@example.test');
    UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Suspended]);

    $this->actingAs($user)->getJson('/_probe')->assertForbidden()
        ->assertJson(['success' => false, 'code' => 'account_inactive']);
    $this->assertGuest();
});

it('ends the session for a locked user until the lock expires', function (): void {
    $user = makeUser('l@example.test');
    $state = UserAccessState::create(['user_id' => $user->id, 'status' => UserStatus::Locked, 'locked_until' => now()->addHour()]);

    $this->actingAs($user)->getJson('/_probe')->assertForbidden()->assertJson(['code' => 'account_inactive']);

    $state->update(['locked_until' => now()->subMinute()]);
    $this->actingAs($user)->getJson('/_probe')->assertOk();
});

it('reads the state once per request', function (): void {
    $user = makeUser('q@example.test');
    Route::middleware(['web', 'access.active', 'access.active'])->get('/_twice', fn () => response('ok'));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($user)->get('/_twice')->assertOk();
    $reads = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'user_access_states'))->count();
    DB::disableQueryLog();

    expect($reads)->toBe(1);
});
