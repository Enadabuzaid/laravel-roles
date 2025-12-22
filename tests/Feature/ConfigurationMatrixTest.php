<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\UsesSingleTenancy;
use Tests\Traits\UsesTeamScopedTenancy;
use Tests\Traits\UsesMultiDatabaseTenancy;
use Tests\Traits\UsesWebGuard;
use Tests\Traits\UsesApiGuard;
use Tests\Traits\SeedsRolesAndPermissions;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

/**
 * Configuration Matrix Tests
 *
 * Tests all guard × tenancy mode combinations.
 * Each combination must pass roles CRUD, permission sync, and matrix endpoint.
 */
class ConfigurationMatrixTest extends TestCase
{
    use UsesSingleTenancy, UsesTeamScopedTenancy, UsesMultiDatabaseTenancy;
    use UsesWebGuard, UsesApiGuard, SeedsRolesAndPermissions;

    /**
     * Configuration combinations to test.
     *
     * @return array
     */
    public static function configurationMatrix(): array
    {
        return [
            'web_single' => ['web', 'single'],
            'web_team_scoped' => ['web', 'team_scoped'],
            'web_multi_database' => ['web', 'multi_database'],
            'api_single' => ['api', 'single'],
            'api_team_scoped' => ['api', 'team_scoped'],
            'api_multi_database' => ['api', 'multi_database'],
        ];
    }

    /**
     * Set up the configuration for a test.
     *
     * @param string $guard
     * @param string $tenancy
     * @return void
     */
    protected function setUpConfiguration(string $guard, string $tenancy): void
    {
        config(['roles.guard' => $guard]);
        config(['roles.tenancy.mode' => $tenancy]);

        // Set up tenancy
        switch ($tenancy) {
            case 'team_scoped':
                $this->setUpTeamScopedTenancy(1);
                break;
            case 'multi_database':
                $this->setUpMultiDatabaseTenancy('tenant_1');
                break;
            default:
                $this->setUpSingleTenancy();
        }

        // Set up guard
        if ($guard === 'api') {
            $this->setUpApiGuard();
        } else {
            $this->setUpWebGuard();
        }
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function can_list_roles(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        Role::create(['name' => 'test-role', 'guard_name' => $guard]);

        $response = $this->getJson('/admin/acl/roles');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function can_create_role(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        $response = $this->postJson('/admin/acl/roles', [
            'name' => "create-{$guard}-{$tenancy}",
            'guard_name' => $guard,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('roles', [
            'name' => "create-{$guard}-{$tenancy}",
            'guard_name' => $guard,
        ]);
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function can_update_role(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        $role = Role::create(['name' => 'original', 'guard_name' => $guard]);

        $response = $this->putJson("/admin/acl/roles/{$role->id}", [
            'name' => 'updated',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'updated']);
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function can_delete_role(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        $role = Role::create(['name' => 'to-delete', 'guard_name' => $guard]);

        $response = $this->deleteJson("/admin/acl/roles/{$role->id}");

        $response->assertOk();
        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function can_sync_permissions(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        Permission::create(['name' => 'sync.list', 'guard_name' => $guard]);
        Permission::create(['name' => 'sync.create', 'guard_name' => $guard]);

        $role = Role::create(['name' => 'sync-role', 'guard_name' => $guard]);

        $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions/diff", [
            'grant' => ['sync.list', 'sync.create'],
            'revoke' => [],
        ]);

        $response->assertOk();
        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('sync.list'));
        $this->assertTrue($role->hasPermissionTo('sync.create'));
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function matrix_endpoint_works(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        Role::create(['name' => 'matrix-role', 'guard_name' => $guard]);
        Permission::create(['name' => 'matrix.perm', 'guard_name' => $guard]);

        $response = $this->getJson('/admin/acl/matrix');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['roles', 'permissions', 'matrix'],
        ]);
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function guard_name_is_correct_on_created_role(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        $response = $this->postJson('/admin/acl/roles', [
            'name' => "guard-test-{$guard}",
            'guard_name' => $guard,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.guard_name', $guard);
    }

    /**
     * @test
     * @group configuration
     * @dataProvider configurationMatrix
     */
    public function role_stats_endpoint_works(string $guard, string $tenancy): void
    {
        $this->setUpConfiguration($guard, $tenancy);

        Role::create(['name' => 'stats-role', 'guard_name' => $guard]);

        $response = $this->getJson('/admin/acl/roles/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['total'],
        ]);
    }
}
