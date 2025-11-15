<?php

use Tests\TestCase;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Events\PermissionCreated;
use Enadstack\LaravelRoles\Events\PermissionUpdated;
use Illuminate\Support\Facades\Event;

uses(TestCase::class);

beforeEach(function () {
    Event::fake();
});

it('creates a permission via API and dispatches event', function () {
    $this->withoutExceptionHandling();

    $response = $this->postJson('/admin/acl/permissions', [
        'name' => 'offers.create',
        'group' => 'offers',
    ]);


    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'guard_name', 'group'],
        ]);

    expect(Permission::where('name', 'offers.create')->exists())->toBeTrue();
    Event::assertDispatched(PermissionCreated::class);
});

it('lists permissions with pagination', function () {
    Permission::create(['name' => 'posts.list', 'guard_name' => 'web', 'group' => 'posts']);
    Permission::create(['name' => 'posts.create', 'guard_name' => 'web', 'group' => 'posts']);

    $response = $this->getJson('/admin/acl/permissions?per_page=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'guard_name'],
            ],
            'links',
            'meta',
        ]);
});

it('filters permissions by group', function () {
    Permission::create(['name' => 'posts.create', 'guard_name' => 'web', 'group' => 'posts']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web', 'group' => 'users']);

    $response = $this->getJson('/admin/acl/permissions?group=posts');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['name'])->toBe('posts.create');
});

it('searches permissions by name', function () {
    Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);

    $response = $this->getJson('/admin/acl/permissions?search=posts');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['name'])->toBe('posts.create');
});

it('shows a single permission', function () {
    $perm = Permission::create(['name' => 'comments.delete', 'guard_name' => 'web']);

    $response = $this->getJson("/admin/acl/permissions/{$perm->id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $perm->id,
                'name' => 'comments.delete',
            ],
        ]);
});

it('updates a permission and dispatches event', function () {
    $perm = Permission::create(['name' => 'old.permission', 'guard_name' => 'web']);

    $response = $this->putJson("/admin/acl/permissions/{$perm->id}", [
        'name' => 'updated.permission',
        'label' => ['en' => 'Updated Permission'],
    ]);
    $response->assertStatus(200);
    expect($perm->fresh()->name)->toBe('updated.permission');
    Event::assertDispatched(PermissionUpdated::class);
});

it('soft deletes a permission', function () {
    $perm = Permission::create(['name' => 'temp.permission', 'guard_name' => 'web']);

    $response = $this->deleteJson("/admin/acl/permissions/{$perm->id}");

    $response->assertStatus(200);
    expect($perm->fresh()->trashed())->toBeTrue();
});

it('restores a soft-deleted permission', function () {
    $perm = Permission::create(['name' => 'restored.permission', 'guard_name' => 'web']);
    $perm->delete();

    $response = $this->postJson("/admin/acl/permissions/{$perm->id}/restore");

    $response->assertStatus(200);
    expect($perm->fresh()->trashed())->toBeFalse();
});

it('force deletes a permission permanently', function () {
    $perm = Permission::create(['name' => 'to-purge.permission', 'guard_name' => 'web']);

    $response = $this->deleteJson("/admin/acl/permissions/{$perm->id}/force");

    $response->assertStatus(200);
    expect(Permission::withTrashed()->find($perm->id))->toBeNull();
});

it('gets permission statistics', function () {
    Permission::create(['name' => 'stat.perm1', 'guard_name' => 'web', 'group' => 'stats']);
    $perm2 = Permission::create(['name' => 'stat.perm2', 'guard_name' => 'web', 'group' => 'stats']);
    $perm2->delete();

    $response = $this->getJson('/admin/acl/permissions-stats');

    $response->assertStatus(200)
        ->assertJsonStructure(['total', 'active', 'deleted', 'assigned', 'unassigned', 'by_group']);
});

it('gets recent permissions', function () {
    Permission::create(['name' => 'recent.perm1', 'guard_name' => 'web']);
    Permission::create(['name' => 'recent.perm2', 'guard_name' => 'web']);

    $response = $this->getJson('/admin/acl/permissions-recent?limit=5');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
        ]);
});

it('gets permission groups', function () {
    $this->withoutExceptionHandling();

    Permission::create(['name' => 'posts.create', 'guard_name' => 'web', 'group' => 'posts']);
    Permission::create(['name' => 'posts.update', 'guard_name' => 'web', 'group' => 'posts']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web', 'group' => 'users']);

    $response = $this->getJson('/admin/acl/permission-groups');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('posts');
    expect($data)->toHaveKey('users');
});

it('validates permission creation with invalid data', function () {
    $response = $this->postJson('/admin/acl/permissions', [
        'name' => '', // empty name should fail
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('validates unique permission name on create', function () {
    Permission::create(['name' => 'duplicate.name', 'guard_name' => 'web']);

    $response = $this->postJson('/admin/acl/permissions', [
        'name' => 'duplicate.name',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
