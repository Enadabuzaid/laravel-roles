<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Http\Support\RoutesRegistrar;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Support\Facades\Route;

it('refuses a suspended user on package routes and lets an active one through', function (): void {
    $suspended = makeOwner('s@example.test');
    $active = makeOwner('a@example.test');
    UserAccessState::create(['user_id' => $suspended->id, 'status' => UserStatus::Suspended]);

    $this->actingAs($suspended)->getJson('/api/access/me')->assertForbidden()->assertJson(['success' => false, 'code' => 'account_inactive']);
    $this->actingAs($active)->getJson('/api/access/me')->assertOk();
});

it('appends access.active after the configured middleware', function (): void {
    RoutesRegistrar::register('roles', __DIR__.'/../../Support/routes/ping.php');
    Route::getRoutes()->refreshNameLookups();
    $middleware = Route::getRoutes()->getByName('access.ping')->gatherMiddleware();

    expect(array_search('access.active', $middleware, true))->toBeGreaterThan(array_search('auth', $middleware, true));
});

it('drops access.active when enforce_active is off', function (): void {
    config(['roles.routes.enforce_active' => false]);
    RoutesRegistrar::register('roles', __DIR__.'/../../Support/routes/ping.php');
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('access.ping')->gatherMiddleware())->not->toContain('access.active');
});
