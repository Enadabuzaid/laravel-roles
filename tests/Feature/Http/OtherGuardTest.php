<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Queries\PermissionsOverviewQuery;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Queries\RolesOverviewQuery;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->admin = makeUser('guard-admin@example.test');
    $this->admin->assignRole(makeRole('guard-admin', [
        'access.roles.view', 'access.roles.create', 'access.roles.update', 'access.roles.delete', 'access.roles.assign',
        'access.permissions.view', 'access.permissions.sync', 'access.users.view',
    ]));
    $this->foreignRole = Role::create(['name' => 'foreign', 'guard_name' => 'api']);
    $this->foreignPermission = Permission::create(['name' => 'foreign.view', 'guard_name' => 'api']);
    $this->foreignRole->givePermissionTo($this->foreignPermission);
    $this->trashedForeign = Role::create(['name' => 'foreign-trashed', 'guard_name' => 'api']);
    $this->trashedForeign->delete();
});

it('answers 404 for a role of another guard on every role route', function (string $method, string $path): void {
    $url = '/api/access/'.str_replace('{role}', (string) $this->foreignRole->id, $path);

    $this->actingAs($this->admin)->json($method, $url, ['confirmation' => 'foreign', 'name' => 'x', 'grant' => ['foreign.view']])->assertNotFound();
    expect(Role::withTrashed()->find($this->foreignRole->id)?->name)->toBe('foreign');
})->with([
    ['GET', 'roles/{role}'],
    ['GET', 'roles/{role}/members'],
    ['PUT', 'roles/{role}'],
    ['DELETE', 'roles/{role}'],
    ['PUT', 'roles/{role}/permissions'],
]);

it('answers 404 for a trashed role of another guard on restore and force delete', function (): void {
    $this->actingAs($this->admin)->postJson("/api/access/roles/{$this->trashedForeign->id}/restore")->assertNotFound();
    $this->actingAs($this->admin)->deleteJson("/api/access/roles/{$this->trashedForeign->id}/force", ['confirmation' => 'foreign-trashed'])->assertNotFound();
    expect(Role::withTrashed()->find($this->trashedForeign->id)->trashed())->toBeTrue();
});

it('answers 404 for a permission of another guard', function (): void {
    $id = $this->foreignPermission->id;
    $this->actingAs($this->admin)->getJson("/api/access/permissions/{$id}")->assertNotFound();
    $this->actingAs($this->admin)->putJson("/api/access/permissions/{$id}", ['label' => ['en' => 'x']])->assertNotFound();
    $this->actingAs($this->admin)->deleteJson("/api/access/permissions/{$id}")->assertNotFound();
    expect(Permission::find($id))->not->toBeNull();
});

it('keeps roles and permissions of another guard out of lists, overviews, modules and the matrix', function (): void {
    $this->foreignPermission->forceFill(['module' => 'foreignmod'])->save();

    $roles = $this->actingAs($this->admin)->getJson('/api/access/roles?status=all&per_page=100')->assertOk()->json('data');
    expect(collect($roles)->pluck('name'))->not->toContain('foreign', 'foreign-trashed');

    $permissions = $this->actingAs($this->admin)->getJson('/api/access/permissions?status=all&per_page=100')->assertOk()->json('data');
    expect(collect($permissions)->pluck('name'))->not->toContain('foreign.view');

    $modules = $this->actingAs($this->admin)->getJson('/api/access/permissions/modules')->assertOk()->json('data');
    expect(collect($modules)->pluck('key'))->not->toContain('foreignmod');

    $matrix = $this->actingAs($this->admin)->getJson('/api/access/matrix')->assertOk()->json('data');
    expect(collect($matrix['roles'])->pluck('name'))->not->toContain('foreign');
});

it('counts only roles and permissions of the configured guard in the overviews', function (): void {
    $roles = RolesOverviewQuery::summary();
    $expectedRoles = Role::query()->where('guard_name', 'web')->count();
    expect($roles['total_roles'])->toBe($expectedRoles)->and($roles['trashed_roles'])->toBe(0)->and($roles['active_roles'])->toBe($expectedRoles);

    $permissions = PermissionsOverviewQuery::summary();
    expect($permissions['total'])->toBe(Permission::query()->where('guard_name', 'web')->count())
        ->and($permissions['total_grants'])->toBe(DB::table('role_has_permissions')->whereIn('role_id', Role::query()->where('guard_name', 'web')->select('id'))->count());
});

it('does not resolve a foreign guard role for assignment', function (): void {
    $target = makeUser('t@example.test');
    $this->actingAs($this->admin)->postJson("/api/access/users/{$target->id}/roles", ['roles' => [$this->foreignRole->id]])->assertStatus(422);
    $this->actingAs($this->admin)->deleteJson("/api/access/users/{$target->id}/roles/{$this->foreignRole->id}")->assertNotFound();
});

it('does not resolve another guard permission in the access check', function (): void {
    $target = makeUser('t2@example.test');
    $target->forceFill([])->save();
    DB::table('model_has_permissions')->insert(['permission_id' => $this->foreignPermission->id, 'model_type' => $target->getMorphClass(), 'model_id' => $target->id]);

    $result = $this->actingAs($this->admin)->getJson("/api/access/permissions/check?user={$target->id}&permission=foreign.view");
    $result->assertOk()->assertJsonPath('data.via', null)->assertJsonPath('data.roles', []);
});
