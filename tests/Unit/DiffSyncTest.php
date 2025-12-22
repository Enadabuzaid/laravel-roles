<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Enadstack\LaravelRoles\Services\RolePermissionSyncService;
use Enadstack\LaravelRoles\Context\SingleTenantContext;
use Enadstack\LaravelRoles\Context\ConfigGuardResolver;
use Enadstack\LaravelRoles\Context\ContextualCacheKeyBuilder;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

/**
 * DiffSyncTest
 *
 * Unit tests for diff-based permission sync logic.
 */
class DiffSyncTest extends TestCase
{
    protected RolePermissionSyncService $syncService;
    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup dependencies
        $tenantContext = new SingleTenantContext();
        $guardResolver = new ConfigGuardResolver();
        $cacheKeyBuilder = new ContextualCacheKeyBuilder($tenantContext, $guardResolver);

        $this->syncService = new RolePermissionSyncService(
            $tenantContext,
            $guardResolver,
            $cacheKeyBuilder
        );

        // Create role
        $this->role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        // Create permissions
        Permission::create(['name' => 'users.list', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'users.create', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'users.update', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'users.delete', 'guard_name' => 'web', 'group' => 'users']);
        Permission::create(['name' => 'roles.list', 'guard_name' => 'web', 'group' => 'roles']);
        Permission::create(['name' => 'roles.create', 'guard_name' => 'web', 'group' => 'roles']);
    }

    /**
     * @test
     */
    public function grants_permissions(): void
    {
        $result = $this->syncService->diffSync(
            $this->role,
            grant: ['users.list', 'users.create'],
            revoke: []
        );

        $this->assertContains('users.list', $result['granted']);
        $this->assertContains('users.create', $result['granted']);
        $this->assertEmpty($result['revoked']);

        $this->role->refresh();
        $this->assertTrue($this->role->hasPermissionTo('users.list'));
        $this->assertTrue($this->role->hasPermissionTo('users.create'));
    }

    /**
     * @test
     */
    public function revokes_permissions(): void
    {
        // First assign permissions
        $this->role->givePermissionTo(['users.list', 'users.create', 'users.update']);

        $result = $this->syncService->diffSync(
            $this->role,
            grant: [],
            revoke: ['users.create', 'users.update']
        );

        $this->assertContains('users.create', $result['revoked']);
        $this->assertContains('users.update', $result['revoked']);
        $this->assertEmpty($result['granted']);

        $this->role->refresh();
        $this->assertTrue($this->role->hasPermissionTo('users.list'));
        $this->assertFalse($this->role->hasPermissionTo('users.create'));
        $this->assertFalse($this->role->hasPermissionTo('users.update'));
    }

    /**
     * @test
     */
    public function handles_grant_and_revoke_together(): void
    {
        // Assign initial permissions
        $this->role->givePermissionTo(['users.list', 'users.delete']);

        $result = $this->syncService->diffSync(
            $this->role,
            grant: ['users.create', 'users.update'],
            revoke: ['users.delete']
        );

        $this->assertContains('users.create', $result['granted']);
        $this->assertContains('users.update', $result['granted']);
        $this->assertContains('users.delete', $result['revoked']);

        $this->role->refresh();
        $this->assertTrue($this->role->hasPermissionTo('users.list'));
        $this->assertTrue($this->role->hasPermissionTo('users.create'));
        $this->assertTrue($this->role->hasPermissionTo('users.update'));
        $this->assertFalse($this->role->hasPermissionTo('users.delete'));
    }

    /**
     * @test
     */
    public function skips_already_granted_permissions(): void
    {
        // Assign permission first
        $this->role->givePermissionTo('users.list');

        $result = $this->syncService->diffSync(
            $this->role,
            grant: ['users.list'],
            revoke: []
        );

        $this->assertEmpty($result['granted']);
        $this->assertNotEmpty($result['skipped']);
        $this->assertEquals('already_granted', $result['skipped'][0]['reason']);
    }

    /**
     * @test
     */
    public function skips_not_assigned_permissions_on_revoke(): void
    {
        // Don't assign any permissions

        $result = $this->syncService->diffSync(
            $this->role,
            grant: [],
            revoke: ['users.list']
        );

        $this->assertEmpty($result['revoked']);
        $this->assertNotEmpty($result['skipped']);
        $this->assertEquals('not_assigned', $result['skipped'][0]['reason']);
    }

    /**
     * @test
     */
    public function skips_nonexistent_permissions(): void
    {
        $result = $this->syncService->diffSync(
            $this->role,
            grant: ['nonexistent.permission'],
            revoke: []
        );

        $this->assertEmpty($result['granted']);
        $this->assertNotEmpty($result['skipped']);
        $this->assertEquals('not_found', $result['skipped'][0]['reason']);
    }

    /**
     * @test
     */
    public function expands_wildcards_in_grant(): void
    {
        $result = $this->syncService->diffSync(
            $this->role,
            grant: ['users.*'],
            revoke: []
        );

        $this->assertCount(4, $result['granted']);
        $this->assertContains('users.list', $result['granted']);
        $this->assertContains('users.create', $result['granted']);
        $this->assertContains('users.update', $result['granted']);
        $this->assertContains('users.delete', $result['granted']);
    }

    /**
     * @test
     */
    public function expands_wildcards_in_revoke(): void
    {
        // Assign all users permissions
        $this->role->givePermissionTo(['users.list', 'users.create', 'users.update', 'users.delete']);

        $result = $this->syncService->diffSync(
            $this->role,
            grant: [],
            revoke: ['users.*']
        );

        $this->assertCount(4, $result['revoked']);

        $this->role->refresh();
        $this->assertFalse($this->role->hasPermissionTo('users.list'));
        $this->assertFalse($this->role->hasPermissionTo('users.create'));
        $this->assertFalse($this->role->hasPermissionTo('users.update'));
        $this->assertFalse($this->role->hasPermissionTo('users.delete'));
    }

    /**
     * @test
     */
    public function handles_empty_arrays(): void
    {
        $result = $this->syncService->diffSync(
            $this->role,
            grant: [],
            revoke: []
        );

        $this->assertEmpty($result['granted']);
        $this->assertEmpty($result['revoked']);
        $this->assertEmpty($result['skipped']);
    }

    /**
     * @test
     */
    public function is_idempotent(): void
    {
        // First sync
        $result1 = $this->syncService->diffSync(
            $this->role,
            grant: ['users.list', 'users.create'],
            revoke: []
        );

        // Second sync with same data
        $result2 = $this->syncService->diffSync(
            $this->role,
            grant: ['users.list', 'users.create'],
            revoke: []
        );

        $this->assertCount(2, $result1['granted']);
        $this->assertEmpty($result2['granted']);
        $this->assertCount(2, $result2['skipped']);
    }
}
