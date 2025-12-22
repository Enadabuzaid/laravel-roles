<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Contracts\RolePermissionSyncServiceContract;

/**
 * WildcardExpansion Unit Tests
 *
 * Tests wildcard expansion logic in permission sync.
 */
class WildcardExpansionTest extends TestCase
{
    use SeedsRolesAndPermissions;

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function expands_star_wildcard_to_all_permissions(): void
    {
        $this->seedDefaultPermissions();

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['*']);

        $allPermissions = Permission::pluck('name')->toArray();
        sort($expanded);
        sort($allPermissions);

        $this->assertEquals($allPermissions, $expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function expands_group_wildcard_to_group_permissions(): void
    {
        $this->seedDefaultPermissions();

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['users.*']);

        $this->assertContains('users.list', $expanded);
        $this->assertContains('users.create', $expanded);
        $this->assertContains('users.show', $expanded);
        $this->assertContains('users.update', $expanded);
        $this->assertContains('users.delete', $expanded);
        $this->assertNotContains('roles.list', $expanded);
        $this->assertNotContains('posts.list', $expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function expands_multiple_group_wildcards(): void
    {
        $this->seedDefaultPermissions();

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['users.*', 'roles.*']);

        $this->assertContains('users.list', $expanded);
        $this->assertContains('roles.list', $expanded);
        $this->assertNotContains('posts.list', $expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function handles_specific_permissions_without_expansion(): void
    {
        $this->seedDefaultPermissions();

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['users.list', 'roles.create']);

        $this->assertCount(2, $expanded);
        $this->assertContains('users.list', $expanded);
        $this->assertContains('roles.create', $expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function handles_mixed_wildcards_and_specific_permissions(): void
    {
        $this->seedDefaultPermissions();

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['users.*', 'roles.list']);

        $this->assertContains('users.list', $expanded);
        $this->assertContains('users.create', $expanded);
        $this->assertContains('roles.list', $expanded);
        $this->assertNotContains('roles.create', $expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function removes_duplicate_permissions_after_expansion(): void
    {
        $this->seedDefaultPermissions();

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['users.*', 'users.list']);

        // Should not have duplicates
        $this->assertEquals(count($expanded), count(array_unique($expanded)));
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function handles_empty_input(): void
    {
        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards([]);

        $this->assertIsArray($expanded);
        $this->assertEmpty($expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function respects_guard_when_expanding(): void
    {
        // Create permissions for different guards
        Permission::create(['name' => 'users.list', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.list', 'guard_name' => 'api']);

        config(['roles.guard' => 'web']);

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['users.*']);

        // Should only include web guard permissions
        $this->assertContains('users.list', $expanded);
        $this->assertContains('users.create', $expanded);
        $this->assertCount(2, $expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function nonexistent_group_returns_empty_for_that_group(): void
    {
        $this->seedDefaultPermissions();

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['nonexistent.*']);

        // Should return empty for non-matching group
        $this->assertEmpty($expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function handles_nested_action_wildcards(): void
    {
        Permission::create(['name' => 'posts.comments.list', 'guard_name' => 'web', 'group' => 'posts']);
        Permission::create(['name' => 'posts.comments.create', 'guard_name' => 'web', 'group' => 'posts']);
        Permission::create(['name' => 'posts.list', 'guard_name' => 'web', 'group' => 'posts']);

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['posts.*']);

        $this->assertContains('posts.list', $expanded);
        $this->assertContains('posts.comments.list', $expanded);
        $this->assertContains('posts.comments.create', $expanded);
    }

    /**
     * @test
     * @group unit
     * @group wildcard
     */
    public function case_sensitivity_is_respected(): void
    {
        Permission::create(['name' => 'Users.list', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.list', 'guard_name' => 'web']);

        $service = app(RolePermissionSyncServiceContract::class);
        $expanded = $service->expandWildcards(['users.*']);

        $this->assertContains('users.list', $expanded);
        $this->assertNotContains('Users.list', $expanded);
    }
}
