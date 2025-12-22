<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * DiffPermission Feature Tests
 *
 * Tests the diff-based permission update endpoint.
 */
class DiffPermissionTest extends TestCase
{
    use SeedsRolesAndPermissions;

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function can_grant_permissions_via_diff_endpoint(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.list', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.create', 'guard_name' => 'web']);

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.list', 'users.create'],
            'revoke' => [],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.list'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function can_revoke_permissions_via_diff_endpoint(): void
    {
        $role = $this->createRoleWithPermissions('editor', ['users.list', 'users.create']);

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => [],
            'revoke' => ['users.list'],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('users.list'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function can_grant_and_revoke_in_same_request(): void
    {
        $role = $this->createRoleWithPermissions('editor', ['users.list']);
        Permission::create(['name' => 'users.create', 'guard_name' => 'web']);

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.create'],
            'revoke' => ['users.list'],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('users.list'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function diff_endpoint_is_idempotent(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.list', 'guard_name' => 'web']);

        // Grant twice
        $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.list'],
            'revoke' => [],
        ])->assertOk();

        $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.list'],
            'revoke' => [],
        ])->assertOk();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.list'));
        // Should only have one permission, not duplicated
        $this->assertEquals(1, $role->permissions->count());
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function can_use_wildcard_in_grant(): void
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->seedDefaultPermissions();

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.*'],
            'revoke' => [],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('users.list'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
        $this->assertTrue($role->hasPermissionTo('users.delete'));
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function can_use_wildcard_in_revoke(): void
    {
        $this->seedDefaultPermissions();
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::where('guard_name', 'web')->get());

        $this->assertTrue($role->hasPermissionTo('users.list'));

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => [],
            'revoke' => ['users.*'],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('users.list'));
        $this->assertFalse($role->hasPermissionTo('users.create'));
        // Other groups should still be assigned
        $this->assertTrue($role->hasPermissionTo('roles.list'));
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function response_includes_granted_and_revoked_lists(): void
    {
        $role = $this->createRoleWithPermissions('editor', ['users.list']);
        Permission::create(['name' => 'users.create', 'guard_name' => 'web']);

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.create'],
            'revoke' => ['users.list'],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'result' => ['granted', 'revoked', 'skipped'],
            ],
        ]);
        $this->assertContains('users.create', $response->json('data.result.granted'));
        $this->assertContains('users.list', $response->json('data.result.revoked'));
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function skipped_permissions_are_reported(): void
    {
        $role = $this->createRoleWithPermissions('editor', ['users.list']);

        // Try to grant a permission that's already assigned
        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.list'],
            'revoke' => [],
        ]);

        $response->assertOk();
        $skipped = $response->json('data.result.skipped');
        // Should report as skipped since already assigned
        $this->assertTrue(
            in_array('users.list', $skipped['already_granted'] ?? []) ||
            count($response->json('data.result.granted')) === 0
        );
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function nonexistent_permissions_are_handled_gracefully(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['nonexistent.permission'],
            'revoke' => [],
        ]);

        $response->assertOk();
        // Should be reported as skipped
        $this->assertNotEmpty($response->json('data.result.skipped'));
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function diff_invalidates_cache(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        $role = Role::create(['name' => 'cached-role', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.list', 'guard_name' => 'web']);

        // Populate cache
        $this->getJson('/admin/acl/matrix')->assertOk();

        // Diff update
        $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['users.list'],
            'revoke' => [],
        ])->assertOk();

        // Matrix should reflect new assignment
        $response = $this->getJson('/admin/acl/matrix');
        $response->assertOk();

        // Find the role in matrix and check it has the permission
        $matrix = $response->json('data.matrix');
        $roleHasPermission = false;
        foreach ($matrix as $row) {
            if ($row['permission_name'] === 'users.list') {
                if (isset($row['roles']['cached-role'])) {
                    $roleHasPermission = $row['roles']['cached-role']['has_permission'];
                }
                break;
            }
        }
        $this->assertTrue($roleHasPermission, 'Cache should be invalidated after diff update');
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function validation_requires_grant_or_revoke_array(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", []);

        $response->assertStatus(422);
    }

    /**
     * @test
     * @group feature
     * @group diff
     */
    public function grant_all_with_star_wildcard(): void
    {
        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->seedDefaultPermissions();

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['*'],
            'revoke' => [],
        ]);

        $response->assertOk();
        $role->refresh();

        // Should have all permissions
        $allPerms = Permission::where('guard_name', 'web')->get();
        foreach ($allPerms as $perm) {
            $this->assertTrue(
                $role->hasPermissionTo($perm->name),
                "Role should have {$perm->name}"
            );
        }
    }
}
