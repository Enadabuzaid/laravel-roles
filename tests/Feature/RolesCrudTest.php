<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\UsesSingleTenancy;
use Tests\Traits\UsesTeamScopedTenancy;
use Tests\Traits\UsesWebGuard;
use Tests\Traits\UsesApiGuard;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

/**
 * Roles CRUD Feature Tests
 *
 * Tests all role CRUD operations across tenancy modes.
 */
class RolesCrudTest extends TestCase
{
    use UsesSingleTenancy, UsesTeamScopedTenancy, UsesSingleTenancy;
    use UsesWebGuard, UsesApiGuard, SeedsRolesAndPermissions;

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_list_roles_in_single_tenant_mode(): void
    {
        $this->setUpSingleTenancy();
        $this->seedDefaultRoles();

        $response = $this->getJson('/admin/acl/roles');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'guard_name'],
            ],
            'meta' => ['current_page', 'total'],
        ]);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_create_role(): void
    {
        $response = $this->postJson('/admin/acl/roles', [
            'name' => 'new-role',
            'guard_name' => 'web',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'new-role');
        $this->assertDatabaseHas('roles', ['name' => 'new-role']);
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_update_role(): void
    {
        $role = Role::create([
            'name' => 'original-name',
            'guard_name' => 'web',
        ]);

        $response = $this->putJson("/admin/acl/roles/{$role->id}", [
            'name' => 'updated-name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'updated-name');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'updated-name']);
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_delete_role(): void
    {
        $role = Role::create([
            'name' => 'to-delete',
            'guard_name' => 'web',
        ]);

        $response = $this->deleteJson("/admin/acl/roles/{$role->id}");

        $response->assertOk();
        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_restore_deleted_role(): void
    {
        $role = Role::create([
            'name' => 'to-restore',
            'guard_name' => 'web',
        ]);
        $role->delete();

        $response = $this->postJson("/admin/acl/roles/{$role->id}/restore");

        $response->assertOk();
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_force_delete_role(): void
    {
        $role = Role::create([
            'name' => 'to-force-delete',
            'guard_name' => 'web',
        ]);

        $response = $this->deleteJson("/admin/acl/roles/{$role->id}/force");

        $response->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function created_role_has_correct_guard_name(): void
    {
        config(['roles.guard' => 'api']);

        $response = $this->postJson('/admin/acl/roles', [
            'name' => 'api-role',
            'guard_name' => 'api',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.guard_name', 'api');
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_search_roles_by_name(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $response = $this->getJson('/admin/acl/roles?search=admin');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.name', 'admin');
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_filter_roles_by_status(): void
    {
        $active = Role::create(['name' => 'active-role', 'guard_name' => 'web', 'status' => 'active']);
        $inactive = Role::create(['name' => 'inactive-role', 'guard_name' => 'web', 'status' => 'inactive']);

        $response = $this->getJson('/admin/acl/roles?status=active');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function pagination_works_correctly(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Role::create(['name' => "role-{$i}", 'guard_name' => 'web']);
        }

        $response = $this->getJson('/admin/acl/roles?per_page=10&page=1');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function can_create_role_with_permissions(): void
    {
        $perm1 = Permission::create(['name' => 'users.list', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'users.create', 'guard_name' => 'web']);

        $response = $this->postJson('/admin/acl/roles', [
            'name' => 'role-with-perms',
            'guard_name' => 'web',
            'permission_ids' => [$perm1->id, $perm2->id],
        ]);

        $response->assertCreated();

        $role = Role::where('name', 'role-with-perms')->first();
        $this->assertTrue($role->hasPermissionTo('users.list'));
        $this->assertTrue($role->hasPermissionTo('users.create'));
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function validation_fails_for_duplicate_role_name(): void
    {
        Role::create(['name' => 'existing-role', 'guard_name' => 'web']);

        $response = $this->postJson('/admin/acl/roles', [
            'name' => 'existing-role',
            'guard_name' => 'web',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /**
     * @test
     * @group feature
     * @group roles
     */
    public function validation_fails_for_empty_role_name(): void
    {
        $response = $this->postJson('/admin/acl/roles', [
            'name' => '',
            'guard_name' => 'web',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /**
     * @test
     * @group feature
     * @group roles
     * @group tenancy
     */
    public function roles_are_team_scoped_in_team_mode(): void
    {
        // Skip this test - team scoped mode requires team_id column in roles table
        // This is tested in ConfigurationMatrixTest with proper table setup
        $this->markTestSkipped('Team-scoped isolation requires team_id column - tested separately.');
    }
}
