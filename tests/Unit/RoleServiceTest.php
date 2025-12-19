<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Enadstack\LaravelRoles\Services\RoleService;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Support\Facades\Cache;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoleService $roleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleService = app(RoleService::class);
    }

    /** @test */
    public function it_can_list_roles_with_pagination()
    {
        Role::factory()->count(5)->create();

        $result = $this->roleService->list([], 3);

        $this->assertCount(3, $result->items());
        $this->assertEquals(5, $result->total());
    }

    /** @test */
    public function it_can_filter_roles_by_search()
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $result = $this->roleService->list(['search' => 'edit'], 10);

        $this->assertCount(1, $result->items());
        $this->assertEquals('editor', $result->items()[0]->name);
    }

    /** @test */
    public function it_can_filter_roles_by_status()
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => 'active']);
        Role::create(['name' => 'editor', 'guard_name' => 'web', 'status' => 'inactive']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web', 'status' => 'active']);

        $result = $this->roleService->list(['status' => 'active'], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_filter_only_deleted_roles()
    {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $role1->delete();
        $role2->delete();

        $result = $this->roleService->list(['only_deleted' => true], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_filter_with_deleted_roles()
    {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $role1->delete();

        $result = $this->roleService->list(['with_deleted' => true], 10);

        $this->assertCount(3, $result->items());
    }

    /** @test */
    public function it_shows_only_non_deleted_by_default()
    {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $role1->delete();

        $result = $this->roleService->list([], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_create_a_role()
    {
        $data = [
            'name' => 'test-role',
            'guard_name' => 'web',
        ];

        $role = $this->roleService->create($data);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('test-role', $role->name);
        $this->assertEquals('active', $role->status);
    }

    /** @test */
    public function it_can_update_a_role()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $updated = $this->roleService->update($role, ['name' => 'super-admin']);

        $this->assertEquals('super-admin', $updated->name);
    }

    /** @test */
    public function it_can_delete_a_role()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->roleService->delete($role);

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
        $this->assertEquals('deleted', $role->fresh()->status);
    }

    /** @test */
    public function it_can_restore_a_role()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->delete();

        $restored = $this->roleService->restore($role->id);

        $this->assertTrue($restored);
        $this->assertNotSoftDeleted('roles', ['id' => $role->id]);
        $this->assertEquals('active', $role->fresh()->status);
    }

    /** @test */
    public function it_can_force_delete_a_role()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->roleService->forceDelete($role);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    /** @test */
    public function it_can_change_role_status()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => 'active']);

        $updated = $this->roleService->changeStatus($role, RolePermissionStatusEnum::INACTIVE);

        $this->assertEquals('inactive', $updated->status);
    }

    /** @test */
    public function it_can_activate_a_role()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => 'inactive']);

        $activated = $this->roleService->activate($role);

        $this->assertEquals('active', $activated->status);
    }

    /** @test */
    public function it_can_deactivate_a_role()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => 'active']);

        $deactivated = $this->roleService->deactivate($role);

        $this->assertEquals('inactive', $deactivated->status);
    }

    /** @test */
    public function it_can_bulk_change_status()
    {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $results = $this->roleService->bulkChangeStatus(
            [$role1->id, $role2->id, $role3->id],
            RolePermissionStatusEnum::INACTIVE
        );

        $this->assertCount(3, $results['success']);
        $this->assertCount(0, $results['failed']);
        $this->assertEquals('inactive', $role1->fresh()->status);
        $this->assertEquals('inactive', $role2->fresh()->status);
        $this->assertEquals('inactive', $role3->fresh()->status);
    }

    /** @test */
    public function it_can_get_role_statistics()
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => 'active']);
        Role::create(['name' => 'editor', 'guard_name' => 'web', 'status' => 'active']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web', 'status' => 'inactive']);
        $deleted = Role::create(['name' => 'deleted', 'guard_name' => 'web']);
        $deleted->delete();

        $stats = $this->roleService->stats();

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['inactive']);
        $this->assertEquals(1, $stats['deleted']);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('growth', $stats);
    }

    /** @test */
    public function it_can_assign_permissions_to_role()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permission1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $updated = $this->roleService->assignPermissions($role, [$permission1->id, $permission2->id]);

        $this->assertCount(2, $updated->permissions);
    }

    /** @test */
    public function it_can_clone_role_with_permissions()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $cloned = $this->roleService->cloneWithPermissions($role, 'super-admin');

        $this->assertEquals('super-admin', $cloned->name);
        $this->assertCount(1, $cloned->permissions);
    }

    /** @test */
    public function it_can_get_recent_roles()
    {
        Role::create(['name' => 'old', 'guard_name' => 'web', 'created_at' => now()->subDays(5)]);
        Role::create(['name' => 'recent', 'guard_name' => 'web', 'created_at' => now()]);

        $recent = $this->roleService->recent(1);

        $this->assertCount(1, $recent);
        $this->assertEquals('recent', $recent->first()->name);
    }

    /** @test */
    public function it_can_bulk_delete_roles()
    {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $results = $this->roleService->bulkDelete([$role1->id, $role2->id]);

        $this->assertCount(2, $results['success']);
        $this->assertSoftDeleted('roles', ['id' => $role1->id]);
        $this->assertSoftDeleted('roles', ['id' => $role2->id]);
    }

    /** @test */
    public function it_can_bulk_restore_roles()
    {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role1->delete();
        $role2->delete();

        $results = $this->roleService->bulkRestore([$role1->id, $role2->id]);

        $this->assertCount(2, $results['success']);
        $this->assertNotSoftDeleted('roles', ['id' => $role1->id]);
        $this->assertNotSoftDeleted('roles', ['id' => $role2->id]);
    }

    /** @test */
    public function it_flushes_cache_after_modifications()
    {
        Cache::shouldReceive('forget')->atLeast()->once();

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->roleService->update($role, ['name' => 'super-admin']);
    }
}

