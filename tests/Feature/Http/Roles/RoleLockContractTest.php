<?php

declare(strict_types=1);

const WRITER = ['access.roles.view', 'access.roles.create', 'access.roles.update', 'access.roles.delete', 'access.roles.assign'];

use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\DB;

function lockedRoleRows(mixed $actor): array
{
    $rows = test()->actingAs($actor)->getJson('/api/access/roles?per_page=100&filter[status]=all')->assertOk()->json('data');

    return collect($rows)->keyBy('name')->all();
}

it('flags the owner role, system roles and roles the actor holds', function (): void {
    makeRole('owner', [], ['is_system' => true]);
    makeRole('admin', [], ['is_system' => true]);
    makeRole('plain');
    $actor = roleWriter(WRITER);
    $own = $actor->roles()->first();
    $gone = makeRole('gone');
    $actor->assignRole($gone);
    $gone->delete();

    $rows = lockedRoleRows($actor);

    expect($rows['owner'])->toMatchArray(['is_owner' => true, 'locked' => true, 'lock_reason' => 'owner'])
        ->and($rows['owner']['abilities'])->toMatchArray(['delete' => false, 'rename' => false, 'update_permissions' => false, 'assign' => false, 'update' => true])
        ->and($rows['admin'])->toMatchArray(['is_owner' => false, 'locked' => true, 'lock_reason' => 'system'])
        ->and($rows['admin']['abilities'])->toMatchArray(['delete' => false, 'rename' => false, 'update_permissions' => true, 'assign' => true])
        ->and($rows['plain'])->toMatchArray(['locked' => false, 'lock_reason' => null])
        ->and($rows['plain']['abilities'])->toMatchArray(['create' => true, 'update' => true, 'rename' => true, 'update_permissions' => true, 'delete' => true, 'assign' => true])
        ->and($rows[$own->name])->toMatchArray(['locked' => true, 'lock_reason' => 'held'])
        ->and($rows[$own->name]['abilities'])->toMatchArray(['update_permissions' => false, 'rename' => true])
        ->and($rows['gone'])->toMatchArray(['lock_reason' => 'held']);
});

it('does not lock held roles for owners and clears update abilities without the permission', function (): void {
    $owner = makeOwner();
    makeRole('plain');
    $rows = lockedRoleRows($owner);

    expect($rows['owner']['lock_reason'])->toBe('owner')->and($rows['owner']['abilities']['assign'])->toBeTrue()
        ->and($rows['plain']['lock_reason'])->toBeNull();

    $viewer = roleWriter(['access.roles.view']);
    $rows = lockedRoleRows($viewer);
    expect($rows['plain']['abilities'])->toMatchArray(['create' => false, 'update' => false, 'rename' => false, 'update_permissions' => false, 'delete' => false, 'assign' => false]);
});

it('computes the lock flags without per-role queries', function (): void {
    $actor = roleWriter(WRITER);
    foreach (range(1, 3) as $i) {
        makeRole("r{$i}");
    }
    $count = function () use ($actor): int {
        $this->actingAs($actor)->getJson('/api/access/roles?per_page=100')->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($actor)->getJson('/api/access/roles?per_page=100')->assertOk();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    $small = $count();
    foreach (range(4, 20) as $i) {
        makeRole("r{$i}");
    }

    expect($count())->toBe($small);
});

it('serves the flags on the detail and update responses too', function (): void {
    $role = makeRole('plain');
    $this->actingAs(roleWriter(WRITER))->getJson("/api/access/roles/{$role->id}")->assertOk()
        ->assertJsonPath('data.lock_reason', null)->assertJsonPath('data.abilities.create', true);
    expect(Role::count())->toBeGreaterThan(0);
});
