<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Enadstack\LaravelRoles\Services\PermissionService;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Enums\RolePermissionStatusEnum;
use Illuminate\Support\Facades\Cache;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissionService = app(PermissionService::class);
    }

    /** @test */
    public function it_can_list_permissions_with_pagination()
    {
        Permission::factory()->count(5)->create(['guard_name' => 'web']);

        $result = $this->permissionService->list(['guard' => 'web'], 3);

        $this->assertCount(3, $result->items());
        $this->assertEquals(5, $result->total());
    }

    /** @test */
    public function it_can_filter_permissions_by_search()
    {
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-users', 'guard_name' => 'web']);

        $result = $this->permissionService->list(['search' => 'posts', 'guard' => 'web'], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_filter_permissions_by_status()
    {
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web', 'status' => 'active']);
        Permission::create(['name' => 'delete-posts', 'guard_name' => 'web', 'status' => 'inactive']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web', 'status' => 'active']);

        $result = $this->permissionService->list(['status' => 'active', 'guard' => 'web'], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_filter_permissions_by_group()
    {
        Permission::create(['name' => 'posts.edit', 'guard_name' => 'web', 'group' => 'posts']);
        Permission::create(['name' => 'posts.delete', 'guard_name' => 'web', 'group' => 'posts']);
        Permission::create(['name' => 'users.view', 'guard_name' => 'web', 'group' => 'users']);

        $result = $this->permissionService->list(['group' => 'posts', 'guard' => 'web'], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_filter_only_deleted_permissions()
    {
        $perm1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $perm1->delete();
        $perm2->delete();

        $result = $this->permissionService->list(['only_deleted' => true, 'guard' => 'web'], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_filter_with_deleted_permissions()
    {
        $perm1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $perm1->delete();

        $result = $this->permissionService->list(['with_deleted' => true, 'guard' => 'web'], 10);

        $this->assertCount(3, $result->items());
    }

    /** @test */
    public function it_shows_only_non_deleted_by_default()
    {
        $perm1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $perm1->delete();

        $result = $this->permissionService->list(['guard' => 'web'], 10);

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function it_can_create_a_permission()
    {
        $data = [
            'name' => 'test-permission',
            'guard_name' => 'web',
        ];

        $permission = $this->permissionService->create($data);

        $this->assertInstanceOf(Permission::class, $permission);
        $this->assertEquals('test-permission', $permission->name);
        $this->assertEquals('active', $permission->status);
    }

    /** @test */
    public function it_can_update_a_permission()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $updated = $this->permissionService->update($permission, ['name' => 'edit-articles']);

        $this->assertEquals('edit-articles', $updated->name);
    }

    /** @test */
    public function it_can_delete_a_permission()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $this->permissionService->delete($permission);

        $this->assertSoftDeleted('permissions', ['id' => $permission->id]);
        $this->assertEquals('deleted', $permission->fresh()->status);
    }

    /** @test */
    public function it_can_restore_a_permission()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permission->delete();

        $restored = $this->permissionService->restore($permission->id);

        $this->assertTrue($restored);
        $this->assertNotSoftDeleted('permissions', ['id' => $permission->id]);
        $this->assertEquals('active', $permission->fresh()->status);
    }

    /** @test */
    public function it_can_force_delete_a_permission()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $this->permissionService->forceDelete($permission);

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    /** @test */
    public function it_can_change_permission_status()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web', 'status' => 'active']);

        $updated = $this->permissionService->changeStatus($permission, RolePermissionStatusEnum::INACTIVE);

        $this->assertEquals('inactive', $updated->status);
    }

    /** @test */
    public function it_can_activate_a_permission()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web', 'status' => 'inactive']);

        $activated = $this->permissionService->activate($permission);

        $this->assertEquals('active', $activated->status);
    }

    /** @test */
    public function it_can_deactivate_a_permission()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web', 'status' => 'active']);

        $deactivated = $this->permissionService->deactivate($permission);

        $this->assertEquals('inactive', $deactivated->status);
    }

    /** @test */
    public function it_can_bulk_change_status()
    {
        $perm1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);
        $perm3 = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $results = $this->permissionService->bulkChangeStatus(
            [$perm1->id, $perm2->id, $perm3->id],
            RolePermissionStatusEnum::INACTIVE
        );

        $this->assertCount(3, $results['success']);
        $this->assertCount(0, $results['failed']);
        $this->assertEquals('inactive', $perm1->fresh()->status);
        $this->assertEquals('inactive', $perm2->fresh()->status);
        $this->assertEquals('inactive', $perm3->fresh()->status);
    }

    /** @test */
    public function it_can_get_permission_statistics()
    {
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web', 'status' => 'active']);
        Permission::create(['name' => 'delete-posts', 'guard_name' => 'web', 'status' => 'active']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web', 'status' => 'inactive']);
        $deleted = Permission::create(['name' => 'deleted', 'guard_name' => 'web']);
        $deleted->delete();

        $stats = $this->permissionService->stats();

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['inactive']);
        $this->assertEquals(1, $stats['deleted']);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('growth', $stats);
    }

    /** @test */
    public function it_can_get_permissions_assigned_to_roles()
    {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $stats = $this->permissionService->stats();

        $this->assertEquals(1, $stats['assigned']);
    }

    /** @test */
    public function it_can_get_recent_permissions()
    {
        Permission::create(['name' => 'old', 'guard_name' => 'web', 'created_at' => now()->subDays(5)]);
        Permission::create(['name' => 'recent', 'guard_name' => 'web', 'created_at' => now()]);

        $recent = $this->permissionService->recent(1);

        $this->assertCount(1, $recent);
        $this->assertEquals('recent', $recent->first()->name);
    }

    /** @test */
    public function it_can_bulk_delete_permissions()
    {
        $perm1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $results = $this->permissionService->bulkDelete([$perm1->id, $perm2->id]);

        $this->assertCount(2, $results['success']);
        $this->assertSoftDeleted('permissions', ['id' => $perm1->id]);
        $this->assertSoftDeleted('permissions', ['id' => $perm2->id]);
    }

    /** @test */
    public function it_can_bulk_restore_permissions()
    {
        $perm1 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);
        $perm1->delete();
        $perm2->delete();

        $results = $this->permissionService->bulkRestore([$perm1->id, $perm2->id]);

        $this->assertCount(2, $results['success']);
        $this->assertNotSoftDeleted('permissions', ['id' => $perm1->id]);
        $this->assertNotSoftDeleted('permissions', ['id' => $perm2->id]);
    }

    /** @test */
    public function it_can_get_grouped_permissions()
    {
        Permission::create(['name' => 'posts.edit', 'guard_name' => 'web', 'group' => 'posts']);
        Permission::create(['name' => 'posts.delete', 'guard_name' => 'web', 'group' => 'posts']);
        Permission::create(['name' => 'users.view', 'guard_name' => 'web', 'group' => 'users']);

        $grouped = $this->permissionService->getGroupedPermissions();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $grouped);
    }

    /** @test */
    public function it_can_get_permission_matrix()
    {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $matrix = $this->permissionService->getPermissionMatrix();

        $this->assertIsArray($matrix);
    }
}

