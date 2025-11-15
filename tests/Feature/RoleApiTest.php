<?php

use Tests\TestCase;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Events\RoleCreated;
use Enadstack\LaravelRoles\Events\RoleUpdated;
use Enadstack\LaravelRoles\Events\RoleDeleted;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;
use Illuminate\Support\Facades\Event;

uses(TestCase::class);

beforeEach(function () {
    Event::fake();
});

it('creates a role via API and dispatches event', function () {
    $response = $this->postJson('/admin/acl/roles', [
        'name' => 'content-editor',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'guard_name', 'created_at'],
        ]);

    expect(Role::where('name', 'content-editor')->exists())->toBeTrue();
    Event::assertDispatched(RoleCreated::class);
});

it('lists roles with pagination', function () {
    Role::create(['name' => 'editor', 'guard_name' => 'web']);
    Role::create(['name' => 'viewer', 'guard_name' => 'web']);

    $response = $this->getJson('/admin/acl/roles?per_page=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'guard_name'],
            ],
            'links',
            'meta',
        ]);
});

it('shows a single role', function () {
    $role = Role::create(['name' => 'moderator', 'guard_name' => 'web']);

    $response = $this->getJson("/admin/acl/roles/{$role->id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $role->id,
                'name' => 'moderator',
            ],
        ]);
});

it('updates a role and dispatches event', function () {
    $role = Role::create(['name' => 'old-name', 'guard_name' => 'web']);

    $response = $this->putJson("/admin/acl/roles/{$role->id}", [
        'name' => 'new-name',
        'label' => ['en' => 'New Name'],
    ]);

    $response->assertStatus(200);
    expect($role->fresh()->name)->toBe('new-name');
    Event::assertDispatched(RoleUpdated::class);
});

it('soft deletes a role and dispatches event', function () {
    $role = Role::create(['name' => 'temp-role', 'guard_name' => 'web']);

    $response = $this->deleteJson("/admin/acl/roles/{$role->id}");

    $response->assertStatus(200);
    expect($role->fresh()->trashed())->toBeTrue();
    Event::assertDispatched(RoleDeleted::class);
});

it('restores a soft-deleted role', function () {
    $role = Role::create(['name' => 'restored-role', 'guard_name' => 'web']);
    $role->delete();

    $response = $this->postJson("/admin/acl/roles/{$role->id}/restore");

    $response->assertStatus(200);
    expect($role->fresh()->trashed())->toBeFalse();
});

it('force deletes a role permanently', function () {
    $role = Role::create(['name' => 'to-be-purged', 'guard_name' => 'web']);

    $response = $this->deleteJson("/admin/acl/roles/{$role->id}/force");

    $response->assertStatus(200);
    expect(Role::withTrashed()->find($role->id))->toBeNull();
    Event::assertDispatched(RoleDeleted::class);
});

it('performs bulk delete on roles', function () {
    $role1 = Role::create(['name' => 'bulk1', 'guard_name' => 'web']);
    $role2 = Role::create(['name' => 'bulk2', 'guard_name' => 'web']);

    $response = $this->postJson('/admin/acl/roles/bulk-delete', [
        'ids' => [$role1->id, $role2->id],
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Bulk delete completed']);

    expect($role1->fresh()->trashed())->toBeTrue();
    expect($role2->fresh()->trashed())->toBeTrue();
});

it('performs bulk restore on roles', function () {
    $role1 = Role::create(['name' => 'bulk-restore1', 'guard_name' => 'web']);
    $role2 = Role::create(['name' => 'bulk-restore2', 'guard_name' => 'web']);
    $role1->delete();
    $role2->delete();

    $response = $this->postJson('/admin/acl/roles/bulk-restore', [
        'ids' => [$role1->id, $role2->id],
    ]);

    $response->assertStatus(200);
    expect($role1->fresh()->trashed())->toBeFalse();
    expect($role2->fresh()->trashed())->toBeFalse();
});

it('assigns permissions to role and dispatches event', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $perm1 = Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
    $perm2 = Permission::create(['name' => 'posts.update', 'guard_name' => 'web']);

    $response = $this->postJson("/admin/acl/roles/{$role->id}/permissions", [
        'permission_ids' => [$perm1->id, $perm2->id],
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Permissions assigned successfully']);

    expect($role->fresh()->hasPermissionTo('posts.create'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('posts.update'))->toBeTrue();
    Event::assertDispatched(PermissionsAssignedToRole::class);
});

it('gets role statistics', function () {
    Role::create(['name' => 'stat-role-1', 'guard_name' => 'web']);
    $role2 = Role::create(['name' => 'stat-role-2', 'guard_name' => 'web']);
    $role2->delete();

    $response = $this->getJson('/admin/acl/roles-stats');

    $response->assertStatus(200)
        ->assertJsonStructure(['total', 'active', 'deleted', 'with_permissions', 'without_permissions']);
});

it('gets recent roles', function () {
    Role::create(['name' => 'recent1', 'guard_name' => 'web']);
    Role::create(['name' => 'recent2', 'guard_name' => 'web']);

    $response = $this->getJson('/admin/acl/roles-recent?limit=5');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
        ]);
});

it('clones a role with its permissions', function () {
    $role = Role::create(['name' => 'original', 'guard_name' => 'web']);
    $perm = Permission::create(['name' => 'test.perm', 'guard_name' => 'web']);
    $role->givePermissionTo($perm);

    $response = $this->postJson("/admin/acl/roles/{$role->id}/clone", [
        'name' => 'cloned-role',
    ]);

    $response->assertStatus(201);

    $cloned = Role::where('name', 'cloned-role')->first();
    expect($cloned)->not->toBeNull();
    expect($cloned->hasPermissionTo('test.perm'))->toBeTrue();
});

it('validates role creation with invalid data', function () {
    $response = $this->postJson('/admin/acl/roles', [
        'name' => '', // empty name should fail
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

