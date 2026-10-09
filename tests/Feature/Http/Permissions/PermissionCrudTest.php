<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Permission;
use Tests\Support\TestUser;

function permWriter(array $permissions = ['access.permissions.sync', 'access.permissions.view']): TestUser
{
    $user = makeUser(uniqid().'@example.test');
    $user->assignRole(makeRole('pw'.uniqid(), $permissions));

    return $user;
}

function manual(array $attrs = []): Permission
{
    $p = makePermission($attrs['name'] ?? 'man.one');
    $p->forceFill(['source' => 'manual', 'module' => 'man', 'label' => ['en' => 'Manual']] + $attrs)->save();

    return $p;
}

it('creates a manual permission and audits it once', function (): void {
    $actor = permWriter();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson('/api/access/permissions', [
        'name' => 'crm.export', 'module' => 'crm', 'label' => ['en' => 'Export CRM', 'ar' => 'تصدير'], 'description' => ['en' => 'd'], 'risk' => 'high', 'source' => 'registry',
    ])->assertCreated()->assertJsonPath('data.name', 'crm.export')->assertJsonPath('data.source', 'manual')->assertJsonPath('data.risk', 'high');

    $log = ActionLog::sole();
    expect($log->event)->toBe('permissions.created')->and($log->module)->toBe('permissions')->and($log->actor_id)->toBe($actor->id);
    expect(Permission::where('name', 'crm.export')->value('source'))->toBe('manual');
});

it('refuses create without permission, unauthenticated', function (): void {
    $this->postJson('/api/access/permissions', [])->assertUnauthorized();
    ActionLog::query()->delete();
    $this->actingAs(permWriter(['access.permissions.view']))->postJson('/api/access/permissions', ['name' => 'a.b', 'module' => 'a', 'label' => ['en' => 'x']])->assertForbidden();
    expect(ActionLog::count())->toBe(0);
});

it('validates create input', function (array $body, string $field): void {
    ActionLog::query()->delete();
    $this->actingAs(permWriter())->postJson('/api/access/permissions', $body)
        ->assertStatus(422)->assertJson(['code' => 'validation_failed'])->assertJsonValidationErrorFor($field, 'errors');
    expect(ActionLog::count())->toBe(0);
})->with([
    'bad name' => [['name' => 'Bad Name', 'module' => 'a', 'label' => ['en' => 'x']], 'name'],
    'bad module' => [['name' => 'a.b', 'module' => 'Bad Module', 'label' => ['en' => 'x']], 'module'],
    'missing module' => [['name' => 'a.b', 'label' => ['en' => 'x']], 'module'],
    'missing default label' => [['name' => 'a.b', 'module' => 'a', 'label' => ['ar' => 'x']], 'label.en'],
    'locale not configured' => [['name' => 'a.b', 'module' => 'a', 'label' => ['en' => 'x', 'fr' => 'y']], 'label'],
    'bad risk' => [['name' => 'a.b', 'module' => 'a', 'label' => ['en' => 'x'], 'risk' => 'extreme'], 'risk'],
]);

it('rejects a duplicate name', function (): void {
    makePermission('dup.name');
    $this->actingAs(permWriter())->postJson('/api/access/permissions', ['name' => 'dup.name', 'module' => 'dup', 'label' => ['en' => 'x']])
        ->assertStatus(422)->assertJsonValidationErrorFor('name', 'errors');
});

it('updates a manual permission with one audit entry', function (): void {
    $p = manual();
    ActionLog::query()->delete();

    $this->actingAs(permWriter())->putJson('/api/access/permissions/'.$p->id, ['label' => ['en' => 'Renamed'], 'risk' => 'medium', 'name' => 'ignored.name'])
        ->assertOk()->assertJsonPath('data.label.en', 'Renamed')->assertJsonPath('data.risk', 'medium')->assertJsonPath('data.name', 'man.one');

    expect(ActionLog::sole()->event)->toBe('permissions.updated');
});

it('does not audit a no-op update', function (): void {
    $p = manual();
    ActionLog::query()->delete();
    $this->actingAs(permWriter())->putJson('/api/access/permissions/'.$p->id, ['risk' => $p->risk])->assertOk();
    expect(ActionLog::count())->toBe(0);
});

it('refuses to update or delete registry-managed permissions', function (): void {
    $p = makePermission('reg.one');
    $p->forceFill(['source' => 'registry', 'deprecated_at' => now()])->save();
    ActionLog::query()->delete();
    $actor = permWriter();

    $this->actingAs($actor)->putJson('/api/access/permissions/'.$p->id, ['label' => ['en' => 'x']])->assertStatus(409)->assertJsonPath('code', 'permission.registry_managed');
    $this->actingAs($actor)->deleteJson('/api/access/permissions/'.$p->id, ['confirmation' => 'reg.one'])->assertStatus(409)->assertJsonPath('code', 'permission.registry_managed');
    expect(ActionLog::count())->toBe(0)->and(Permission::where('name', 'reg.one')->exists())->toBeTrue();
});

it('refuses to delete a permission held by a role or user', function (): void {
    $p = manual();
    makeRole('holder')->givePermissionTo($p);
    $direct = manual(['name' => 'man.two']);
    makeUser('d@example.test')->givePermissionTo($direct);
    $actor = permWriter();

    $this->actingAs($actor)->deleteJson('/api/access/permissions/'.$p->id, ['confirmation' => 'man.one'])
        ->assertStatus(409)->assertJsonPath('code', 'permission.in_use');
    $this->actingAs($actor)->deleteJson('/api/access/permissions/'.$direct->id, ['confirmation' => 'man.two'])
        ->assertStatus(409)->assertJsonPath('code', 'permission.in_use');
});

it('needs the exact name to delete a free manual permission, then audits a warning', function (): void {
    $p = manual();
    $actor = permWriter();
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson('/api/access/permissions/'.$p->id)->assertStatus(403)->assertJsonPath('code', 'guardrail.confirmation_mismatch');
    $this->actingAs($actor)->deleteJson('/api/access/permissions/'.$p->id, ['confirmation' => 'wrong'])->assertStatus(403);
    expect(Permission::whereKey($p->id)->exists())->toBeTrue()->and(ActionLog::count())->toBe(0);

    $this->actingAs($actor)->deleteJson('/api/access/permissions/'.$p->id, ['confirmation' => 'man.one'])->assertNoContent();

    $log = ActionLog::sole();
    expect(Permission::whereKey($p->id)->exists())->toBeFalse()->and($log->event)->toBe('permissions.deleted')->and($log->severity)->toBe('warning');
});

it('refuses writes without the sync permission', function (): void {
    $p = manual();
    $viewer = permWriter(['access.permissions.view']);
    $this->actingAs($viewer)->putJson('/api/access/permissions/'.$p->id, ['risk' => 'high'])->assertForbidden();
    $this->actingAs($viewer)->deleteJson('/api/access/permissions/'.$p->id, ['confirmation' => 'man.one'])->assertForbidden();
});
