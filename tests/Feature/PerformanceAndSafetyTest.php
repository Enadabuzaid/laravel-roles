<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * Performance and Safety Tests
 *
 * Tests query counts, cache invalidation, and data safety.
 */
class PerformanceAndSafetyTest extends TestCase
{
    use SeedsRolesAndPermissions;

    /**
     * @test
     * @group performance
     */
    public function role_listing_uses_limited_queries(): void
    {
        // Create 20 roles
        for ($i = 1; $i <= 20; $i++) {
            Role::create(['name' => "role-{$i}", 'guard_name' => 'web']);
        }

        Cache::flush();

        $queryCount = $this->assertNoN1(function () {
            return $this->getJson('/admin/acl/roles');
        }, 10, 'Role listing should not cause N+1');
    }

    /**
     * @test
     * @group performance
     */
    public function matrix_endpoint_uses_max_queries(): void
    {
        $this->seedRolesWithPermissions();
        Cache::flush();

        $this->assertNoN1(function () {
            return $this->getJson('/admin/acl/matrix');
        }, 5, 'Matrix endpoint should use at most 5 queries');
    }

    /**
     * @test
     * @group performance
     */
    public function permission_listing_uses_limited_queries(): void
    {
        $this->seedDefaultPermissions();
        Cache::flush();

        $this->assertNoN1(function () {
            return $this->getJson('/admin/acl/permissions');
        }, 10, 'Permission listing should not cause N+1');
    }

    /**
     * @test
     * @group cache
     */
    public function creating_role_invalidates_cache(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        // Populate cache
        $this->getJson('/admin/acl/roles')->assertOk();

        // Create new role
        $this->postJson('/admin/acl/roles', [
            'name' => 'new-cached-role',
            'guard_name' => 'web',
        ])->assertCreated();

        // Cache should include new role
        $response = $this->getJson('/admin/acl/roles');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('new-cached-role', $names);
    }

    /**
     * @test
     * @group cache
     */
    public function updating_role_invalidates_cache(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        $role = Role::create(['name' => 'cached-role', 'guard_name' => 'web']);

        // Populate cache
        $this->getJson('/admin/acl/roles')->assertOk();

        // Update role
        $this->putJson("/admin/acl/roles/{$role->id}", [
            'name' => 'updated-cached-role',
        ])->assertOk();

        // Cache should reflect update
        $response = $this->getJson('/admin/acl/roles');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('updated-cached-role', $names);
        $this->assertNotContains('cached-role', $names);
    }

    /**
     * @test
     * @group cache
     */
    public function deleting_role_invalidates_cache(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        $role = Role::create(['name' => 'to-delete-cached', 'guard_name' => 'web']);

        // Populate cache
        $this->getJson('/admin/acl/roles')->assertOk();

        // Delete role
        $this->deleteJson("/admin/acl/roles/{$role->id}")->assertOk();

        // Cache should not include deleted role
        $response = $this->getJson('/admin/acl/roles');
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('to-delete-cached', $names);
    }

    /**
     * @test
     * @group cache
     */
    public function sync_permissions_invalidates_cache(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        $role = Role::create(['name' => 'sync-cache-role', 'guard_name' => 'web']);
        Permission::create(['name' => 'cache.perm', 'guard_name' => 'web']);

        // Populate matrix cache
        $this->getJson('/admin/acl/matrix')->assertOk();

        // Sync permissions
        $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['cache.perm'],
            'revoke' => [],
        ])->assertOk();

        // Matrix should reflect new assignment
        $response = $this->getJson('/admin/acl/matrix');
        $matrix = $response->json('data.matrix');

        $hasPermission = false;
        foreach ($matrix as $row) {
            if ($row['permission_name'] === 'cache.perm') {
                if (isset($row['roles']['sync-cache-role'])) {
                    $hasPermission = $row['roles']['sync-cache-role']['has_permission'];
                }
            }
        }
        $this->assertTrue($hasPermission, 'Cache should be invalidated after permission sync');
    }

    /**
     * @test
     * @group cache
     */
    public function diff_update_invalidates_cache(): void
    {
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        $role = Role::create(['name' => 'diff-cache-role', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'diff.perm', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        // Populate cache
        $this->getJson('/admin/acl/matrix')->assertOk();

        // Revoke via diff
        $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => [],
            'revoke' => ['diff.perm'],
        ])->assertOk();

        // Verify revocation is reflected
        $response = $this->getJson('/admin/acl/matrix');
        $matrix = $response->json('data.matrix');

        $hasPermission = true;
        foreach ($matrix as $row) {
            if ($row['permission_name'] === 'diff.perm') {
                if (isset($row['roles']['diff-cache-role'])) {
                    $hasPermission = $row['roles']['diff-cache-role']['has_permission'];
                }
            }
        }
        $this->assertFalse($hasPermission, 'Cache should reflect revoked permission');
    }

    /**
     * @test
     * @group safety
     */
    public function cannot_access_other_guards_roles(): void
    {
        Role::create(['name' => 'web-only', 'guard_name' => 'web']);
        Role::create(['name' => 'api-only', 'guard_name' => 'api']);

        config(['roles.guard' => 'web']);

        $response = $this->getJson('/admin/acl/roles?guard=web');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('web-only', $names);
        $this->assertNotContains('api-only', $names);
    }

    /**
     * @test
     * @group safety
     */
    public function soft_deleted_roles_not_in_default_listing(): void
    {
        $role = Role::create(['name' => 'soft-deleted', 'guard_name' => 'web']);
        $role->delete();

        $response = $this->getJson('/admin/acl/roles');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('soft-deleted', $names);
    }

    /**
     * @test
     * @group safety
     */
    public function with_trashed_includes_soft_deleted(): void
    {
        $role = Role::create(['name' => 'trashed-included', 'guard_name' => 'web']);
        $role->delete();

        $response = $this->getJson('/admin/acl/roles?with_trashed=true');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('trashed-included', $names);
    }

    /**
     * @test
     * @group safety
     */
    public function only_trashed_returns_only_deleted(): void
    {
        Role::create(['name' => 'active-role', 'guard_name' => 'web']);
        $deleted = Role::create(['name' => 'deleted-role', 'guard_name' => 'web']);
        $deleted->delete();

        $response = $this->getJson('/admin/acl/roles?only_trashed=true');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('deleted-role', $names);
        $this->assertNotContains('active-role', $names);
    }
}
