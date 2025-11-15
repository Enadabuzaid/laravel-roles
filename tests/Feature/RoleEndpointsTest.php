<?php

use Tests\TestCase;

uses(TestCase::class);

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;

it('can attach and detach single permission and clone role', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $perm = Permission::create(['name' => 'offers.create', 'guard_name' => 'web']);

    // Endpoints from provider are auto-loaded

    // Attach
    $response = $this->postJson('/admin/acl/roles/'.$role->id.'/permission', [
        'permission_id' => $perm->id,
    ]);
    $response->assertStatus(200);
    expect($role->fresh()->hasPermissionTo('offers.create'))->toBeTrue();

    // Detach
    $response = $this->deleteJson('/admin/acl/roles/'.$role->id.'/permission', [
        'permission_id' => $perm->id,
    ]);
    $response->assertStatus(200);
    expect($role->fresh()->hasPermissionTo('offers.create'))->toBeFalse();

    // Clone
    $response = $this->postJson('/admin/acl/roles/'.$role->id.'/clone', [
        'name' => 'editor-copy',
    ]);
    $response->assertStatus(201);
    expect(Role::where('name', 'editor-copy')->exists())->toBeTrue();
});
