<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Core\Queries\AccessCheckQuery;
use Enadstack\LaravelRoles\Http\Middleware\RequireAccess;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestUser;

/** @param list<string> $permissions */
function followUpActor(array $permissions): TestUser
{
    $user = makeUser(uniqid().'@example.test');
    $user->assignRole(makeRole('fu'.uniqid(), $permissions));

    return $user;
}

it('lets a creator with only create make an empty role but not one with permissions', function (): void {
    $actor = followUpActor(['access.roles.create']);
    makePermission('reports.view');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson('/api/access/roles', ['name' => 'empty-one', 'label' => ['en' => 'E']])->assertCreated();

    $this->actingAs($actor)->postJson('/api/access/roles', ['name' => 'loaded', 'label' => ['en' => 'L'], 'permissions' => ['reports.view']])
        ->assertForbidden()->assertJson(['code' => 'forbidden']);
    expect(Role::where('name', 'loaded')->exists())->toBeFalse();

    $this->actingAs($actor)->postJson('/api/access/roles', ['name' => 'loaded', 'label' => ['en' => 'L'], 'permissions' => []])->assertCreated();
});

it('lets a creator with create and update make a role with permissions', function (): void {
    $actor = followUpActor(['access.roles.create', 'access.roles.update', 'reports.view']);

    $this->actingAs($actor)->postJson('/api/access/roles', ['name' => 'loaded', 'label' => ['en' => 'L'], 'permissions' => ['reports.view']])->assertCreated();
    expect(Role::where('name', 'loaded')->first()->permissions->pluck('name')->all())->toBe(['reports.view']);
});

it('rejects an unsupported access middleware argument at request time', function (): void {
    $request = Request::create('/x');
    $owner = makeOwner('mw-owner@example.test');
    $request->setUserResolver(fn () => $owner);

    expect(fn () => (new RequireAccess)->handle($request, fn () => response('ok'), 'all'))->toThrow(InvalidArgumentException::class, 'only [access:any]');
    expect((new RequireAccess)->handle($request, fn () => response('ok'), 'any')->getContent())->toBe('ok');
});

it('ignores deprecated and other-guard access permissions in access:any and me', function (): void {
    $deprecatedOnly = makeUser('dep@example.test');
    $deprecatedOnly->assignRole(makeRole('dep', ['access.roles.view']));
    Permission::where('name', 'access.roles.view')->update(['deprecated_at' => now()]);

    $this->actingAs($deprecatedOnly)->getJson('/api/access/me')->assertForbidden();

    Permission::where('name', 'access.roles.view')->update(['deprecated_at' => null]);
    $this->actingAs($deprecatedOnly->fresh())->getJson('/api/access/me')->assertOk()->assertJsonPath('data.permissions', ['access.roles.view']);

    $foreign = Permission::create(['name' => 'access.permissions.view', 'guard_name' => 'api']);
    $user = makeUser('foreign@example.test');
    $user->permissions()->attach($foreign);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user)->getJson('/api/access/me')->assertForbidden();

    $mixed = makeUser('mixed@example.test');
    $mixed->assignRole(makeRole('mixed', ['access.roles.view', 'access.roles.create']));
    $mixed->permissions()->attach($foreign);
    Permission::where('name', 'access.roles.create')->update(['deprecated_at' => now()]);
    $this->actingAs($mixed->fresh())->getJson('/api/access/me')->assertOk()->assertJsonPath('data.permissions', ['access.roles.view']);
});

it('detects owners in the access check by the configured guard only', function (): void {
    makeRole('owner', [], ['is_system' => true]);
    Role::create(['name' => 'owner', 'guard_name' => 'api']);
    $user = makeUser('apiowner@example.test');
    $apiOwner = Role::where('name', 'owner')->where('guard_name', 'api')->first();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->roles()->attach($apiOwner);

    expect(AccessCheckQuery::check($user->id, 'anything.at.all')['allowed'])->toBeFalse();
});

it('clears the previous default only within the configured guard on update', function (): void {
    $apiDefault = Role::create(['name' => 'api-default', 'guard_name' => 'api', 'is_default' => true]);
    $webDefault = makeRole('web-default', [], ['is_default' => true]);
    $target = makeRole('target');

    $this->actingAs(followUpActor(['access.roles.update']))->putJson("/api/access/roles/{$target->id}", ['is_default' => true])->assertOk();

    expect($apiDefault->fresh()->is_default)->toBeTrue()
        ->and($webDefault->fresh()->is_default)->toBeFalse()
        ->and($target->fresh()->is_default)->toBeTrue();
});

it('lets an owner restore a non-owner role with one audit entry', function (): void {
    $role = makeRole('restorable', ['reports.view']);
    $role->delete();
    $owner = makeOwner();
    ActionLog::query()->delete();

    $this->actingAs($owner)->postJson("/api/access/roles/{$role->id}/restore")->assertOk()->assertJsonPath('data.name', 'restorable');

    expect(ActionLog::where('event', 'roles.restored')->count())->toBe(1)->and(ActionLog::count())->toBe(1);
});

it('lets a non-owner with delete restore a role whose permissions they hold', function (): void {
    $actor = followUpActor(['access.roles.delete', 'reports.view']);
    $role = makeRole('shared', ['reports.view']);
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/roles/{$role->id}/restore")->assertOk();

    expect(ActionLog::sole()->event)->toBe('roles.restored')->and(Role::find($role->id))->not->toBeNull();
});
