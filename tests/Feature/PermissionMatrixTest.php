<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * PermissionMatrix Feature Tests
 *
 * Tests the permission matrix endpoint with performance assertions.
 */
class PermissionMatrixTest extends TestCase
{
    use SeedsRolesAndPermissions;

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function matrix_endpoint_returns_correct_structure(): void
    {
        $this->seedRolesWithPermissions();

        $response = $this->getJson('/admin/acl/matrix');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'roles',
                'permissions',
                'matrix',
            ],
        ]);
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function matrix_contains_all_roles(): void
    {
        $data = $this->seedRolesWithPermissions();

        $response = $this->getJson('/admin/acl/matrix');

        $response->assertOk();
        $roles = $response->json('data.roles');
        $this->assertCount(3, $roles);

        $roleNames = collect($roles)->pluck('name')->toArray();
        $this->assertContains('admin', $roleNames);
        $this->assertContains('editor', $roleNames);
        $this->assertContains('viewer', $roleNames);
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function matrix_contains_all_permissions(): void
    {
        $this->seedRolesWithPermissions();

        $response = $this->getJson('/admin/acl/matrix');

        $response->assertOk();
        $permissions = $response->json('data.permissions');
        $this->assertCount(15, $permissions); // 3 groups × 5 actions

        $permNames = collect($permissions)->pluck('name')->toArray();
        $this->assertContains('users.list', $permNames);
        $this->assertContains('roles.create', $permNames);
        $this->assertContains('posts.delete', $permNames);
    }

    /**
     * @test
     * @group feature
     * @group matrix
     * @group performance
     */
    public function matrix_endpoint_uses_max_5_queries(): void
    {
        $this->seedRolesWithPermissions();
        Cache::flush();

        $response = $this->assertNoN1(function () {
            return $this->getJson('/admin/acl/matrix');
        }, 5, 'Matrix endpoint should use at most 5 queries');

        $response->assertOk();
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function matrix_mapping_shows_correct_assignments(): void
    {
        $data = $this->seedRolesWithPermissions();

        $response = $this->getJson('/admin/acl/matrix');

        $response->assertOk();
        $matrix = $response->json('data.matrix');

        // Find a row for 'users.list' and check admin has it
        foreach ($matrix as $row) {
            if ($row['permission_name'] === 'users.list') {
                // Admin should have this permission
                $this->assertTrue(
                    $row['roles']['admin']['has_permission'] ?? false,
                    'Admin should have users.list permission'
                );
                break;
            }
        }
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function grouped_matrix_endpoint_returns_groups(): void
    {
        $this->seedRolesWithPermissions();

        $response = $this->getJson('/admin/acl/matrix/grouped');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'roles',
                'groups',
            ],
        ]);
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function matrix_respects_guard_filter(): void
    {
        // Create permissions for different guards
        Role::create(['name' => 'web-role', 'guard_name' => 'web']);
        Role::create(['name' => 'api-role', 'guard_name' => 'api']);
        Permission::create(['name' => 'web.perm', 'guard_name' => 'web']);
        Permission::create(['name' => 'api.perm', 'guard_name' => 'api']);

        $response = $this->getJson('/admin/acl/matrix?guard=web');

        $response->assertOk();
        $permissions = $response->json('data.permissions');
        $permNames = collect($permissions)->pluck('name')->toArray();

        $this->assertContains('web.perm', $permNames);
        $this->assertNotContains('api.perm', $permNames);
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function matrix_is_cached(): void
    {
        $this->seedRolesWithPermissions();
        config(['roles.cache.enabled' => true]);
        Cache::flush();

        // First request - should hit database
        $this->getJson('/admin/acl/matrix')->assertOk();

        // Second request - should use cache (fewer queries)
        $this->startQueryCount();
        $this->getJson('/admin/acl/matrix')->assertOk();
        $count = $this->stopQueryCount();

        // Cached response should use very few queries
        $this->assertLessThanOrEqual(2, $count, 'Cached matrix should use minimal queries');
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function matrix_with_many_roles_still_performs_well(): void
    {
        // Create 20 roles and 50 permissions
        for ($i = 1; $i <= 20; $i++) {
            Role::create(['name' => "role-{$i}", 'guard_name' => 'web']);
        }

        for ($i = 1; $i <= 50; $i++) {
            Permission::create([
                'name' => "permission-{$i}",
                'guard_name' => 'web',
                'group' => 'group-' . ceil($i / 10),
            ]);
        }

        Cache::flush();

        $response = $this->assertNoN1(function () {
            return $this->getJson('/admin/acl/matrix');
        }, 5, 'Matrix with many roles/permissions should still be efficient');

        $response->assertOk();
        $this->assertCount(20, $response->json('data.roles'));
        $this->assertCount(50, $response->json('data.permissions'));
    }

    /**
     * @test
     * @group feature
     * @group matrix
     */
    public function empty_matrix_returns_valid_structure(): void
    {
        // No roles or permissions

        $response = $this->getJson('/admin/acl/matrix');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['roles', 'permissions', 'matrix'],
        ]);
        $this->assertEmpty($response->json('data.roles'));
        $this->assertEmpty($response->json('data.permissions'));
        $this->assertEmpty($response->json('data.matrix'));
    }
}
