<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

function modulePermission(string $name, string $module, string $risk = 'low'): Permission
{
    return Permission::create(['name' => $name, 'guard_name' => 'web', 'module' => $module, 'risk' => $risk]);
}

it('shows a user profile with state, roles and effective permission count', function (): void {
    $actor = usersActor();
    $ann = makeUser('ann@example.test');
    makeState($ann, 'locked', [
        'status_reason' => 'Too many attempts', 'activated_at' => '2026-01-01 08:00:00', 'last_login_at' => '2026-06-01 09:00:00',
        'last_login_ip' => '10.1.1.1', 'failed_login_count' => 5, 'locked_until' => '2026-06-01 10:00:00',
        'mfa_enabled_at' => '2026-02-01 08:00:00', 'mfa_method' => 'totp', 'password_changed_at' => '2026-03-01 08:00:00',
    ]);
    modulePermission('billing.view', 'billing');
    modulePermission('billing.edit', 'billing');
    modulePermission('reports.view', 'reports');
    $ann->assignRole(makeRole('finance', ['billing.view', 'billing.edit']));
    $ann->assignRole(makeRole('analyst', ['billing.view', 'reports.view']));
    $ann->givePermissionTo('reports.view');
    $ann->givePermissionTo(makePermission('misc.thing'));

    $data = $this->actingAs($actor)->getJson("/api/access/users/{$ann->id}")->assertOk()->assertJsonPath('success', true)->json('data');

    expect($data)->toMatchArray([
        'id' => $ann->id, 'email' => 'ann@example.test', 'status' => 'locked', 'is_self' => false, 'is_owner' => false,
        'effective_permissions_count' => 4, 'mfa_enabled' => true,
    ])
        ->and($data['state'])->toMatchArray(['status' => 'locked', 'reason' => 'Too many attempts', 'last_login_ip' => '10.1.1.1', 'failed_login_count' => 5, 'mfa_method' => 'totp'])
        ->and($data['state']['locked_until'])->toStartWith('2026-06-01T10:00:00')
        ->and($data['state']['activated_at'])->toStartWith('2026-01-01T08:00:00')
        ->and($data['state']['password_changed_at'])->toStartWith('2026-03-01T08:00:00')
        ->and(array_keys($data['state']))->toBe(['status', 'reason', 'activated_at', 'last_login_at', 'last_login_ip', 'failed_login_count', 'locked_until', 'mfa_enabled_at', 'mfa_method', 'password_changed_at'])
        ->and(collect($data['roles'])->pluck('permissions_count', 'name')->all())->toBe(['analyst' => 2, 'finance' => 2])
        ->and(array_keys($data['abilities']))->toBe(['view', 'update', 'suspend', 'delete', 'assign']);
});

it('shows a user without a state row as active and marks the current user', function (): void {
    $actor = usersActor();

    $data = $this->actingAs($actor)->getJson("/api/access/users/{$actor->id}")->assertOk()->json('data');

    expect($data['is_self'])->toBeTrue()
        ->and($data['lock_reason'])->toBe('self')
        ->and($data['status'])->toBe('active')
        ->and($data['state'])->toMatchArray(['status' => 'active', 'failed_login_count' => 0, 'last_login_at' => null, 'mfa_enabled_at' => null])
        ->and($data['effective_permissions_count'])->toBe(1);
});

it('shows an owner profile', function (): void {
    $owner = makeOwner();
    $actor = usersActor();

    $data = $this->actingAs($actor)->getJson("/api/access/users/{$owner->id}")->assertOk()->json('data');

    expect($data['is_owner'])->toBeTrue()->and($data['lock_reason'])->toBe('owner')->and($data['roles'][0]['name'])->toBe('owner');
});

it('answers 404 envelopes for unknown and malformed users and 403/401 without access', function (): void {
    $actor = usersActor();

    $this->actingAs($actor)->getJson('/api/access/users/999999')->assertNotFound()->assertJson(['success' => false, 'code' => 'not_found']);
    $this->actingAs($actor)->getJson('/api/access/users/abc')->assertNotFound()->assertJson(['success' => false, 'code' => 'not_found']);
    $this->actingAs($actor)->getJson('/api/access/users/99999999999999999999')->assertNotFound();
    $this->actingAs($actor)->getJson('/api/access/users/999999/permissions')->assertNotFound()->assertJson(['code' => 'not_found']);
    $this->actingAs($actor)->getJson('/api/access/users/1x/permissions')->assertNotFound();

    $plain = makeUser('plain@example.test');
    $this->actingAs($plain)->getJson("/api/access/users/{$actor->id}")->assertForbidden();
    $this->actingAs($plain)->getJson("/api/access/users/{$actor->id}/permissions")->assertForbidden();
    auth()->logout();
    $this->getJson("/api/access/users/{$actor->id}")->assertUnauthorized();
    $this->getJson("/api/access/users/{$actor->id}/permissions")->assertUnauthorized();
});

it('groups effective permissions by module with their sources', function (): void {
    $actor = usersActor();
    $ann = makeUser('ann@example.test');
    modulePermission('billing.view', 'billing');
    modulePermission('billing.refund', 'billing', 'high');
    modulePermission('reports.view', 'reports');
    modulePermission('loose.thing', 'other');
    $ann->assignRole(makeRole('finance', ['billing.view', 'billing.refund']));
    $ann->assignRole(makeRole('analyst', ['billing.view', 'reports.view']));
    $ann->givePermissionTo('billing.view');
    $ann->givePermissionTo('loose.thing');
    $trashed = makeRole('gone', ['reports.view']);
    $ann->assignRole($trashed);
    $trashed->delete();

    $response = $this->actingAs($actor)->getJson("/api/access/users/{$ann->id}/permissions")->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.is_owner', false);

    $groups = collect($response->json('data'))->keyBy('module');

    expect($groups->keys()->all())->toBe(['billing', 'other', 'reports'])
        ->and($groups['billing']['permissions_count'])->toBe(2)
        ->and(collect($groups['billing']['permissions'])->pluck('name')->all())->toBe(['billing.refund', 'billing.view'])
        ->and($groups['billing']['permissions'][0])->toMatchArray(['name' => 'billing.refund', 'risk' => 'high', 'sources' => ['role:finance'], 'roles' => ['finance'], 'direct' => false, 'is_deprecated' => false])
        ->and($groups['billing']['permissions'][1])->toMatchArray(['name' => 'billing.view', 'sources' => ['role:analyst', 'role:finance', 'direct'], 'roles' => ['analyst', 'finance'], 'direct' => true])
        ->and($groups['other']['permissions'][0]['sources'])->toBe(['direct'])
        ->and($groups['reports']['permissions'][0]['sources'])->toBe(['role:analyst'])
        ->and(array_keys($groups['reports']['permissions'][0]))->toContain('id', 'label_text', 'risk', 'module');
});

it('returns an empty list for a user without permissions', function (): void {
    $actor = usersActor();
    $none = makeUser('none@example.test');

    $this->actingAs($actor)->getJson("/api/access/users/{$none->id}/permissions")->assertOk()
        ->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
});

it('uses constant queries for profile and permissions however many roles and permissions a user has', function (): void {
    Model::preventLazyLoading();
    $actor = usersActor();
    $small = makeUser('small@example.test');
    $big = makeUser('big@example.test');
    for ($i = 1; $i <= 12; $i++) {
        $big->assignRole(makeRole("r{$i}", ["p{$i}.view", "p{$i}.edit"]));
    }
    $small->assignRole(makeRole('only', ['one.view']));

    $count = function (string $url) use ($actor): int {
        $this->actingAs($actor);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($url)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    foreach (['', '/permissions'] as $suffix) {
        $count("/api/access/users/{$small->id}{$suffix}"); // warm the permission cache
        expect($count("/api/access/users/{$big->id}{$suffix}"))->toBe($count("/api/access/users/{$small->id}{$suffix}"));
    }

    Model::preventLazyLoading(false);
});
