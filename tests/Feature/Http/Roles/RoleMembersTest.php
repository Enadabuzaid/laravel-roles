<?php

declare(strict_types=1);

use Tests\Support\TestUser;

function membersActor(bool $withUsersView = true): TestUser
{
    $user = makeUser('actor@example.test');
    $user->assignRole(makeRole('viewer', ['access.roles.view', ...($withUsersView ? ['access.users.view'] : [])]));

    return $user;
}

it('lists, paginates and searches the members of a role', function (): void {
    $role = makeRole('team');
    foreach (['ann', 'bob', 'cat'] as $name) {
        TestUser::create(['name' => $name, 'email' => "{$name}@example.test", 'password' => 'x'])->assignRole($role);
    }
    makeUser('outsider@example.test');

    $actor = membersActor();
    $this->actingAs($actor)->getJson("/api/access/roles/{$role->id}/members?per_page=2")->assertOk()
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0', ['id' => TestUser::where('name', 'ann')->value('id'), 'name' => 'ann', 'email' => 'ann@example.test']);

    $found = $this->actingAs($actor)->getJson("/api/access/roles/{$role->id}/members?search=bob")->assertOk()->json('data');
    expect($found)->toHaveCount(1)->and($found[0]['name'])->toBe('bob');
});

it('requires both roles view and users view', function (): void {
    $role = makeRole('team');

    $this->actingAs(membersActor(false))->getJson("/api/access/roles/{$role->id}/members")->assertForbidden();
});
