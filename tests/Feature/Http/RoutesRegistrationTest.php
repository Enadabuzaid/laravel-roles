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
        ->and($route->gatherMiddleware())->toContain('web', 'auth', 'throttle:access');
});

it('throttles through the named access limiter and honours the configured limit', function (): void {
    config(['roles.http.rate_limit' => 2]);
    RoutesRegistrar::register('roles', pingFixture());
    $user = makeUser();

    $this->actingAs($user)->getJson('/api/access/ping')->assertOk();
    $this->actingAs($user)->getJson('/api/access/ping')->assertOk();
    $this->actingAs($user)->getJson('/api/access/ping')->assertStatus(429)->assertJson(['success' => false, 'code' => 'too_many_requests']);

    $other = makeUser('other@example.test');
    $this->actingAs($other)->getJson('/api/access/ping')->assertOk();
});

it('still parses the legacy attempts,minutes string', function (): void {
    config(['roles.http.rate_limit' => '1,5']);
    expect(RoutesRegistrar::rateLimit())->toBe(['attempts' => 1, 'minutes' => 5]);

    config(['roles.http.rate_limit' => '30']);
    expect(RoutesRegistrar::rateLimit())->toBe(['attempts' => 30, 'minutes' => 1]);

    RoutesRegistrar::register('roles', pingFixture());
    $user = makeUser();
    config(['roles.http.rate_limit' => '1,5']);
    $this->actingAs($user)->getJson('/api/access/ping')->assertOk();
    $this->actingAs($user)->getJson('/api/access/ping')->assertStatus(429);
});

it('does not throttle when the rate limit is disabled', function (): void {
    config(['roles.http.rate_limit' => null]);
    RoutesRegistrar::register('roles', pingFixture());

    expect(RoutesRegistrar::rateLimit())->toBeNull()->and(pingRoute()->gatherMiddleware())->not->toContain('throttle:access');
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
