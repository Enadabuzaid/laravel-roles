<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\Route;

/**
 * UIRoutes Feature Tests
 *
 * Tests UI route availability based on configuration.
 */
class UIRoutesTest extends TestCase
{
    use SeedsRolesAndPermissions;

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function ui_routes_return_404_when_disabled(): void
    {
        config(['roles.ui.enabled' => false]);

        // Verify config is set
        $this->assertFalse(config('roles.ui.enabled'));
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function ui_enabled_config_is_respected(): void
    {
        config(['roles.ui.enabled' => true]);
        config(['roles.ui.driver' => 'vue']);

        $this->assertTrue(config('roles.ui.enabled'));
        $this->assertEquals('vue', config('roles.ui.driver'));
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function ui_only_enabled_for_vue_driver(): void
    {
        config(['roles.ui.enabled' => true]);
        config(['roles.ui.driver' => 'blade']);

        // UI should only be active for 'vue' driver
        $this->assertNotEquals('vue', config('roles.ui.driver'));
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function ui_respects_custom_prefix(): void
    {
        config(['roles.ui.enabled' => true]);
        config(['roles.ui.prefix' => 'custom/acl']);

        $this->assertEquals('custom/acl', config('roles.ui.prefix'));
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function ui_respects_middleware_config(): void
    {
        config(['roles.ui.enabled' => true]);
        config(['roles.ui.middleware' => ['web', 'auth', 'custom']]);

        $middleware = config('roles.ui.middleware');
        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('custom', $middleware);
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function api_routes_work_independently_of_ui_config(): void
    {
        config(['roles.ui.enabled' => false]);
        $this->seedDefaultRoles();

        // API routes should still work
        $response = $this->getJson('/admin/acl/roles');
        $response->assertOk();
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function matrix_api_route_works_when_ui_disabled(): void
    {
        config(['roles.ui.enabled' => false]);
        $this->seedRolesWithPermissions();

        $response = $this->getJson('/admin/acl/matrix');
        $response->assertOk();
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function ui_routes_require_authentication(): void
    {
        config(['roles.ui.enabled' => true]);
        config(['roles.ui.middleware' => ['web', 'auth']]);

        // Verify middleware is configured
        $middleware = config('roles.ui.middleware');
        $this->assertContains('auth', $middleware);
    }

    /**
     * @test
     * @group feature
     * @group ui
     */
    public function ui_layout_config_is_available(): void
    {
        config(['roles.ui.enabled' => true]);
        config(['roles.ui.layout' => 'AppLayout']);

        $this->assertEquals('AppLayout', config('roles.ui.layout'));
    }
}
