<?php

declare(strict_types=1);

use Tests\Support\TestUser;

function showViewer(): TestUser
{
    $user = makeUser(uniqid().'@example.test');
    $user->assignRole(makeRole('viewer'.uniqid(), ['access.roles.view', 'access.roles.assign']));

    return $user;
}

it('shows a role with permissions and abilities', function (): void {
    $role = makeRole('auditor', ['access.roles.view']);

    $this->actingAs(showViewer())->getJson("/api/access/roles/{$role->id}")->assertOk()
        ->assertJsonPath('data.name', 'auditor')
        ->assertJsonPath('data.permissions_count', 1)
        ->assertJsonPath('data.permissions.0.name', 'access.roles.view')
        ->assertJsonPath('data.abilities', ['update' => false, 'delete' => false, 'assign' => true]);
});

it('shows a trashed role', function (): void {
    $role = makeRole('old');
    $role->delete();

    $this->actingAs(showViewer())->getJson("/api/access/roles/{$role->id}")->assertOk()->assertJsonPath('data.name', 'old');
});

it('answers a 404 envelope for a missing role and does not shadow the overview route', function (): void {
    $this->actingAs(showViewer())->getJson('/api/access/roles/99999')->assertNotFound()->assertJson(['success' => false, 'code' => 'not_found']);
    $this->actingAs(showViewer())->getJson('/api/access/roles/overview')->assertOk();
});
