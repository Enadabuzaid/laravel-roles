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

it('answers a 404 envelope for non-numeric and oversized ids', function (): void {
    $actor = showViewer();

    foreach (['abc', '99999999999999999999', '9223372036854775807'] as $id) {
        $this->actingAs($actor)->getJson("/api/access/roles/{$id}")->assertNotFound()->assertJson(['success' => false]);
        $this->actingAs($actor)->getJson("/api/access/roles/{$id}/members")->assertNotFound()->assertJson(['success' => false]);
    }
});

it('answers 401 and 403 envelopes for show and overview', function (): void {
    $role = makeRole('x');

    $this->actingAs(makeUser(uniqid().'@example.test'));
    $this->getJson("/api/access/roles/{$role->id}")->assertForbidden()->assertJson(['success' => false]);
    $this->getJson('/api/access/roles/overview')->assertForbidden()->assertJson(['success' => false]);

    auth()->forgetGuards();
    $this->getJson("/api/access/roles/{$role->id}")->assertUnauthorized()->assertJson(['success' => false]);
    $this->getJson('/api/access/roles/overview')->assertUnauthorized()->assertJson(['success' => false]);
    $this->getJson("/api/access/roles/{$role->id}/members")->assertUnauthorized()->assertJson(['success' => false]);
});
