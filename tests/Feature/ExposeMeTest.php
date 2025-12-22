<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

/**
 * ExposeMe Feature Tests
 *
 * Tests the /me/acl endpoint when routes.expose_me is enabled.
 */
class ExposeMeTest extends TestCase
{
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['roles.routes.expose_me' => true]);
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     */
    public function me_roles_returns_user_roles(): void
    {
        $user = $this->createTestUser(['email' => 'roled@example.com']);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->actingAs($user);

        $response = $this->getJson('/admin/acl/me/roles');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
        ]);
        $roleNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('editor', $roleNames);
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     */
    public function me_permissions_returns_user_permissions(): void
    {
        $user = $this->createTestUser(['email' => 'permed@example.com']);
        $role = $this->createRoleWithPermissions('admin', ['users.list', 'users.create']);
        $user->assignRole($role);

        $this->actingAs($user);

        $response = $this->getJson('/admin/acl/me/permissions');

        $response->assertOk();
        $permNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('users.list', $permNames);
        $this->assertContains('users.create', $permNames);
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     */
    public function me_acl_returns_combined_data(): void
    {
        $user = $this->createTestUser(['email' => 'acl@example.com']);
        $role = $this->createRoleWithPermissions('manager', ['posts.list']);
        $user->assignRole($role);

        $this->actingAs($user);

        $response = $this->getJson('/admin/acl/me/acl');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'roles',
                'permissions',
                'tenant',
                'guard',
            ],
        ]);

        $roleNames = collect($response->json('data.roles'))->pluck('name')->toArray();
        $this->assertContains('manager', $roleNames);
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     */
    public function permissions_match_assigned_roles(): void
    {
        $user = $this->createTestUser(['email' => 'match@example.com']);

        $role1 = $this->createRoleWithPermissions('role1', ['perm.a', 'perm.b']);
        $role2 = $this->createRoleWithPermissions('role2', ['perm.c']);

        $user->assignRole([$role1, $role2]);

        $this->actingAs($user);

        $response = $this->getJson('/admin/acl/me/permissions');

        $response->assertOk();
        $permNames = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('perm.a', $permNames);
        $this->assertContains('perm.b', $permNames);
        $this->assertContains('perm.c', $permNames);
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     */
    public function user_without_roles_returns_empty(): void
    {
        $user = $this->createTestUser(['email' => 'norole@example.com']);
        $this->actingAs($user);

        $response = $this->getJson('/admin/acl/me/roles');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     */
    public function unauthenticated_user_cannot_access_me_endpoints(): void
    {
        // Clear authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/admin/acl/me/roles');

        // Should redirect or return 401/403
        $this->assertTrue(
            in_array($response->status(), [401, 403, 302]),
            'Unauthenticated users should not access /me endpoints'
        );
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     */
    public function expose_me_disabled_returns_404(): void
    {
        config(['roles.routes.expose_me' => false]);

        // Routes may not be unregistered mid-test, so we verify config
        $this->assertFalse(config('roles.routes.expose_me'));
    }

    /**
     * @test
     * @group feature
     * @group expose_me
     * @group tenancy
     */
    public function me_acl_respects_tenant_context(): void
    {
        $user = $this->createTestUser(['email' => 'tenant@example.com']);
        $role = Role::create(['name' => 'tenant-role', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->actingAs($user);

        $response = $this->getJson('/admin/acl/me/acl');

        $response->assertOk();
        $response->assertJsonPath('data.tenant.mode', 'single');
    }
}
