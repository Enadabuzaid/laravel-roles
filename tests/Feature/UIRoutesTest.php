<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/**
 * UIRoutesTest
 *
 * Tests UI route registration, middleware, and accessibility.
 */
class UIRoutesTest extends TestCase
{
    /**
     * Test that UI routes are registered when UI is enabled.
     */
    public function test_ui_routes_registered_when_enabled(): void
    {
        Config::set('roles.ui.enabled', true);
        Config::set('roles.ui.driver', 'vue');

        // Re-register routes
        $this->app->make('router')->getRoutes()->refreshNameLookups();

        // Check that UI routes exist
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'roles.ui.'))
            ->pluck('uri')
            ->toArray();

        $this->assertNotEmpty($routes, 'UI routes should be registered when enabled');
    }

    /**
     * Test that UI routes use web middleware.
     */
    public function test_ui_routes_use_web_middleware(): void
    {
        Config::set('roles.ui.enabled', true);
        Config::set('roles.ui.driver', 'vue');
        Config::set('roles.ui.middleware', ['web', 'auth']);

        $this->app->make('router')->getRoutes()->refreshNameLookups();

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'roles.ui.'));

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware, "UI route {$route->uri()} should use 'web' middleware");
        }
    }

    /**
     * Test that API routes are separate from UI routes.
     */
    public function test_api_routes_use_configured_middleware(): void
    {
        Config::set('roles.routes.middleware', ['web', 'auth']);

        $this->app->make('router')->getRoutes()->refreshNameLookups();

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'roles.') && !str_starts_with($route->getName() ?? '', 'roles.ui.'));

        $this->assertNotEmpty($routes, 'API routes should be registered');

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            // Should contain the configured middleware
            $this->assertTrue(
                in_array('web', $middleware) || in_array('api', $middleware),
                "API route {$route->uri()} should have web or api middleware"
            );
        }
    }

    /**
     * Test UI routes return redirect for unauthenticated users (web behavior).
     */
    public function test_ui_routes_redirect_unauthenticated_users(): void
    {
        Config::set('roles.ui.enabled', true);
        Config::set('roles.ui.driver', 'vue');

        $prefix = config('roles.ui.prefix', 'admin/acl');

        $response = $this->get("/{$prefix}/roles");

        // Web middleware should redirect to login
        $response->assertStatus(302);
    }

    /**
     * Test that API routes return 401 JSON for unauthenticated requests.
     */
    public function test_api_routes_return_json_for_unauthenticated(): void
    {
        // When using API middleware with auth:sanctum, should return 401 JSON
        Config::set('roles.routes.middleware', ['api', 'auth:sanctum']);

        $prefix = config('roles.routes.prefix', 'admin/acl');

        $response = $this->getJson("/{$prefix}/roles");

        // For sanctum, unauthenticated should get 401
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Test UI routes have correct names.
     */
    public function test_ui_routes_have_correct_names(): void
    {
        Config::set('roles.ui.enabled', true);
        Config::set('roles.ui.driver', 'vue');

        $expectedNames = [
            'roles.ui.roles.index',
            'roles.ui.roles.create',
            'roles.ui.roles.show',
            'roles.ui.roles.edit',
            'roles.ui.permissions.index',
            'roles.ui.matrix',
        ];

        foreach ($expectedNames as $name) {
            $this->assertTrue(
                Route::has($name),
                "Route '{$name}' should exist"
            );
        }
    }
}
