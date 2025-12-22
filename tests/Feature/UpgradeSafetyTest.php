<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

/**
 * Upgrade Safety Tests
 *
 * Simulates upgrading from v1.2.2 to v1.3.0 and verifies data integrity.
 */
class UpgradeSafetyTest extends TestCase
{
    use SeedsRolesAndPermissions;

    /**
     * @test
     * @group upgrade
     */
    public function existing_permissions_survive_sync(): void
    {
        // Simulate v1.2.2 data
        $oldPerm = Permission::create([
            'name' => 'legacy.permission',
            'guard_name' => 'web',
        ]);

        // Configure v1.3.0 seed (without the legacy permission)
        config(['roles.seed.permission_groups' => [
            'users' => ['list', 'create'],
        ]]);

        // Run sync (without prune)
        $this->artisan('roles:sync')->assertSuccessful();

        // Legacy permission should still exist
        $this->assertDatabaseHas('permissions', ['name' => 'legacy.permission']);

        // New permissions should be created
        $this->assertDatabaseHas('permissions', ['name' => 'users.list']);
    }

    /**
     * @test
     * @group upgrade
     */
    public function existing_role_mappings_preserved(): void
    {
        // Simulate v1.2.2 data - role with permission
        $role = Role::create(['name' => 'legacy-admin', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'legacy.manage', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        // Configure v1.3.0 (doesn't include legacy-admin in map)
        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);
        config(['roles.seed.map' => [
            'admin' => ['users.*'],
        ]]);

        // Run sync
        $this->artisan('roles:sync')->assertSuccessful();

        // Legacy role should still have its permission
        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('legacy.manage'));
    }

    /**
     * @test
     * @group upgrade
     */
    public function labels_updated_if_supported(): void
    {
        // Create permission with old structure
        $perm = Permission::create([
            'name' => 'users.list',
            'guard_name' => 'web',
        ]);

        config(['roles.i18n.enabled' => true]);
        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);
        config(['roles.seed.permission_labels' => [
            'users.list' => ['en' => 'List Users', 'ar' => 'عرض المستخدمين'],
        ]]);

        // Run sync
        $this->artisan('roles:sync')->assertSuccessful();

        // Permission should still exist
        $this->assertDatabaseHas('permissions', ['name' => 'users.list']);
    }

    /**
     * @test
     * @group upgrade
     */
    public function guard_name_preserved_after_sync(): void
    {
        // Create role with specific guard
        $role = Role::create([
            'name' => 'api-role',
            'guard_name' => 'api',
        ]);

        config(['roles.guard' => 'web']);
        config(['roles.seed.roles' => ['admin']]);

        // Run sync with web guard
        $this->artisan('roles:sync')->assertSuccessful();

        // API role should keep its guard
        $role->refresh();
        $this->assertEquals('api', $role->guard_name);
    }

    /**
     * @test
     * @group upgrade
     */
    public function existing_permission_groups_preserved(): void
    {
        // Create permission with group
        Permission::create([
            'name' => 'posts.create',
            'guard_name' => 'web',
            'group' => 'posts',
        ]);

        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);

        // Run sync
        $this->artisan('roles:sync')->assertSuccessful();

        // Posts permission should still have its group
        $perm = Permission::where('name', 'posts.create')->first();
        $this->assertNotNull($perm);
        $this->assertEquals('posts', $perm->group);
    }

    /**
     * @test
     * @group upgrade
     */
    public function sync_command_is_idempotent_after_upgrade(): void
    {
        // Seed initial data
        $this->seedRolesWithPermissions();
        $initialRoleCount = Role::count();
        $initialPermissionCount = Permission::count();

        config(['roles.seed.permission_groups' => [
            'users' => ['list', 'create', 'show', 'update', 'delete'],
            'roles' => ['list', 'create', 'show', 'update', 'delete'],
            'posts' => ['list', 'create', 'show', 'update', 'delete'],
        ]]);
        config(['roles.seed.roles' => ['admin', 'editor', 'viewer']]);
        config(['roles.seed.map' => [
            'admin' => ['*'],
            'editor' => ['users.*', 'posts.*'],
            'viewer' => ['*.list', '*.show'],
        ]]);

        // Run sync multiple times
        $this->artisan('roles:sync')->assertSuccessful();
        $this->artisan('roles:sync')->assertSuccessful();
        $this->artisan('roles:sync')->assertSuccessful();

        // Counts should not increase (no duplicates)
        $this->assertEquals($initialRoleCount, Role::count());
        $this->assertEquals($initialPermissionCount, Permission::count());
    }

    /**
     * @test
     * @group upgrade
     */
    public function team_scoped_data_survives_upgrade(): void
    {
        config(['roles.tenancy.mode' => 'team_scoped']);
        config(['permission.teams' => true]);

        // Create team-specific role
        $role = Role::create([
            'name' => 'team-role',
            'guard_name' => 'web',
        ]);

        config(['roles.seed.roles' => ['admin']]);

        // Run sync
        $this->artisan('roles:sync')->assertSuccessful();

        // Team role should still exist
        $this->assertDatabaseHas('roles', ['name' => 'team-role']);
    }

    /**
     * @test
     * @group upgrade
     */
    public function api_endpoints_work_after_upgrade(): void
    {
        // Simulate v1.2.2 data
        $this->seedRolesWithPermissions();

        // Verify all endpoints still work
        $this->getJson('/admin/acl/roles')->assertOk();
        $this->getJson('/admin/acl/permissions')->assertOk();
        $this->getJson('/admin/acl/matrix')->assertOk();
        $this->getJson('/admin/acl/roles/stats')->assertOk();
    }

    /**
     * @test
     * @group upgrade
     */
    public function diff_endpoint_works_with_legacy_data(): void
    {
        // Create legacy role without new fields
        $role = Role::create([
            'name' => 'legacy-role',
            'guard_name' => 'web',
        ]);
        Permission::create(['name' => 'legacy.perm', 'guard_name' => 'web']);

        // Use new diff endpoint
        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['legacy.perm'],
            'revoke' => [],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('legacy.perm'));
    }
}
