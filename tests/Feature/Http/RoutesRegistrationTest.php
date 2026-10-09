<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Http\Support\RoutesRegistrar;
use Illuminate\Support\Facades\Route;

function pingRoute(): ?Illuminate\Routing\Route
{
    Route::getRoutes()->refreshNameLookups();

    return Route::getRoutes()->getByName('access.ping');
}

function pingFixture(): string
{
    return __DIR__.'/../../Support/routes/ping.php';
}

it('registers a module routes file under the prefix, name prefix, middleware and throttle', function (): void {
    RoutesRegistrar::register('roles', pingFixture());

    $route = pingRoute();

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('api/access/ping')
        ->and($route->gatherMiddleware())->toContain('web', 'auth', 'throttle:60,1');
});

it('uses the configured rate limit', function (): void {
    config(['roles.http.rate_limit' => '10,2']);
    RoutesRegistrar::register('roles', pingFixture());

    expect(pingRoute()->gatherMiddleware())->toContain('throttle:10,2');
});

it('skips the routes file when the module is disabled', function (): void {
    config(['roles.modules.roles' => false]);
    RoutesRegistrar::register('roles', pingFixture());

    expect(pingRoute())->toBeNull();
});

it('answers under the prefix with the envelope for 401 and 404', function (): void {
    RoutesRegistrar::register('roles', pingFixture());

    $this->getJson('/api/access/ping')->assertUnauthorized()->assertJson(['success' => false, 'code' => 'unauthenticated']);

    $this->actingAs(makeUser())->getJson('/api/access/ping')->assertOk()->assertJson(['pong' => true]);
    $this->actingAs(makeUser('b@example.test'))->getJson('/api/access/missing')->assertNotFound()->assertJson(['success' => false, 'code' => 'not_found']);
});
