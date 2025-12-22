<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * DiffEndpointTest
 *
 * Feature tests for the diff sync endpoint.
 */
class DiffEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'users.list', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'users.create', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'users.update', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'users.delete', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'roles.list', 'guard_name' => 'web', 'group' => 'roles']);
        Permission::create(['name' => 'roles.create', 'guard_name' => 'web', 'group' => 'roles']);
    }

    /**
     * @test
     */
    public function can_grant_permissions_via_diff(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->createAdminUser())
            ->postJson(route('roles.permissions.diff', $role), [
                'grant' => ['users.list', 'users.create'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.result.granted', ['users.list', 'users.create']);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.list'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
    }

    /**
     * @test
     */
    public function can_revoke_permissions_via_diff(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $role->givePermissionTo(['users.list', 'users.create', 'users.update']);

        $response = $this->actingAs($this->createAdminUser())
            ->postJson(route('roles.permissions.diff', $role), [
                'revoke' => ['users.create', 'users.update'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.result.revoked', ['users.create', 'users.update']);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.list'));
        $this->assertFalse($role->hasPermissionTo('users.create'));
        $this->assertFalse($role->hasPermissionTo('users.update'));
    }

    /**
     * @test
     */
    public function can_grant_and_revoke_in_same_request(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $role->givePermissionTo(['users.delete']);

        $response = $this->actingAs($this->createAdminUser())
            ->postJson(route('roles.permissions.diff', $role), [
                'grant' => ['users.list', 'users.create'],
                'revoke' => ['users.delete'],
            ]);

        $response->assertOk();

        $result = $response->json('data.result');
        $this->assertContains('users.list', $result['granted']);
        $this->assertContains('users.create', $result['granted']);
        $this->assertContains('users.delete', $result['revoked']);
    }

    /**
     * @test
     */
    public function supports_wildcard_expansion(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->createAdminUser())
            ->postJson(route('roles.permissions.diff', $role), [
                'grant' => ['users.*'],
            ]);

        $response->assertOk();

        $result = $response->json('data.result');
        $this->assertCount(4, $result['granted']);
        $this->assertContains('users.list', $result['granted']);
        $this->assertContains('users.create', $result['granted']);
        $this->assertContains('users.update', $result['granted']);
        $this->assertContains('users.delete', $result['granted']);
    }

    /**
     * @test
     */
    public function returns_skipped_for_already_assigned(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $role->givePermissionTo('users.list');

        $response = $this->actingAs($this->createAdminUser())
            ->postJson(route('roles.permissions.diff', $role), [
                'grant' => ['users.list'],
            ]);

        $response->assertOk();

        $result = $response->json('data.result');
        $this->assertEmpty($result['granted']);
        $this->assertNotEmpty($result['skipped']);
    }

    /**
     * @test
     */
    public function validates_payload(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->createAdminUser())
            ->postJson(route('roles.permissions.diff', $role), [
                'grant' => 'invalid_string', // Should be array
            ]);

        $response->assertUnprocessable();
    }

    /**
     * Create an admin user for testing.
     *
     * @return mixed
     */
    protected function createAdminUser()
    {
        $userClass = config('auth.providers.users.model', \App\Models\User::class);

        if (!class_exists($userClass)) {
            $this->markTestSkipped('User model not found');
        }

        $user = new $userClass([
            'name' => 'Admin',
            'email' => 'admin@test.com',
        ]);

        // Give super-admin role for testing
        if (method_exists($user, 'assignRole')) {
            Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
            $user->assignRole('super-admin');
        }

        return $user;
    }
}
