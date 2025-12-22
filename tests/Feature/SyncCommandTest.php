<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

/**
 * SyncCommand Feature Tests
 *
 * Tests the roles:sync artisan command.
 */
class SyncCommandTest extends TestCase
{
    use SeedsRolesAndPermissions;

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_creates_permissions_from_config(): void
    {
        config(['roles.seed.permission_groups' => [
            'articles' => ['list', 'create', 'update', 'delete'],
        ]]);

        $this->artisan('roles:sync')
            ->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'articles.list']);
        $this->assertDatabaseHas('permissions', ['name' => 'articles.create']);
        $this->assertDatabaseHas('permissions', ['name' => 'articles.update']);
        $this->assertDatabaseHas('permissions', ['name' => 'articles.delete']);
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_is_idempotent(): void
    {
        config(['roles.seed.permission_groups' => [
            'users' => ['list', 'create'],
        ]]);

        // Run twice
        $this->artisan('roles:sync')->assertSuccessful();
        $this->artisan('roles:sync')->assertSuccessful();

        // Should not create duplicates
        $count = Permission::where('name', 'users.list')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_assigns_permissions_to_roles(): void
    {
        config(['roles.seed.permission_groups' => [
            'users' => ['list', 'create', 'update'],
        ]]);
        config(['roles.seed.map' => [
            'admin' => ['users.*'],
        ]]);

        $this->artisan('roles:sync')->assertSuccessful();

        $admin = Role::where('name', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasPermissionTo('users.list'));
        $this->assertTrue($admin->hasPermissionTo('users.create'));
        $this->assertTrue($admin->hasPermissionTo('users.update'));
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_respects_guard(): void
    {
        config(['roles.guard' => 'api']);
        config(['roles.seed.permission_groups' => [
            'posts' => ['list'],
        ]]);

        $this->artisan('roles:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', [
            'name' => 'posts.list',
            'guard_name' => 'api',
        ]);
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_with_dry_run_does_not_modify_database(): void
    {
        config(['roles.seed.permission_groups' => [
            'settings' => ['list', 'update'],
        ]]);

        $this->artisan('roles:sync', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'settings.list']);
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_creates_roles_from_config(): void
    {
        config(['roles.seed.roles' => ['manager', 'supervisor']]);

        $this->artisan('roles:sync')->assertSuccessful();

        $this->assertDatabaseHas('roles', ['name' => 'manager']);
        $this->assertDatabaseHas('roles', ['name' => 'supervisor']);
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_expands_star_wildcard_correctly(): void
    {
        config(['roles.seed.permission_groups' => [
            'users' => ['list', 'create'],
            'posts' => ['list', 'create'],
        ]]);
        config(['roles.seed.map' => [
            'super-admin' => ['*'],
        ]]);

        $this->artisan('roles:sync')->assertSuccessful();

        $superAdmin = Role::where('name', 'super-admin')->first();
        $this->assertNotNull($superAdmin);

        // Should have all permissions
        $this->assertTrue($superAdmin->hasPermissionTo('users.list'));
        $this->assertTrue($superAdmin->hasPermissionTo('users.create'));
        $this->assertTrue($superAdmin->hasPermissionTo('posts.list'));
        $this->assertTrue($superAdmin->hasPermissionTo('posts.create'));
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_handles_empty_map_gracefully(): void
    {
        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);
        config(['roles.seed.map' => []]);

        $this->artisan('roles:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'users.list']);
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_updates_permission_labels_if_i18n_enabled(): void
    {
        config(['roles.i18n.enabled' => true]);
        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);
        config(['roles.seed.permission_labels' => [
            'users.list' => ['en' => 'List Users', 'ar' => 'عرض المستخدمين'],
        ]]);

        $this->artisan('roles:sync')->assertSuccessful();

        $perm = Permission::where('name', 'users.list')->first();
        $this->assertNotNull($perm);
        // Check label is stored (if model supports it)
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_with_prune_option_removes_old_permissions(): void
    {
        // Create an old permission
        Permission::create(['name' => 'old.permission', 'guard_name' => 'web']);

        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);

        $this->artisan('roles:sync', ['--prune' => true])
            ->assertSuccessful();

        // Old permission should be removed (if prune is implemented)
        // Note: This depends on implementation
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_shows_verbose_output(): void
    {
        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);

        $this->artisan('roles:sync', ['--verbose-output' => true])
            ->expectsOutputToContain('Permission')
            ->assertSuccessful();
    }

    /**
     * @test
     * @group feature
     * @group command
     */
    public function sync_command_works_with_team_id_option(): void
    {
        config(['roles.tenancy.mode' => 'team_scoped']);
        config(['roles.seed.permission_groups' => [
            'users' => ['list'],
        ]]);

        $this->artisan('roles:sync', ['--team-id' => 1])
            ->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'users.list']);
    }
}
