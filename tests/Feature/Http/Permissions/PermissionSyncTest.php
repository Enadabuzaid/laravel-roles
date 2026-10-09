<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Enadstack\LaravelRoles\Models\Permission;

function useRegistry(string $file): void
{
    app()->instance(PermissionRegistry::class, new PermissionRegistry(dirname(__DIR__, 2).'/../Support/registry/'.$file.'.php', []));
}

function syncer()
{
    $user = makeUser('sy'.uniqid().'@example.test');
    $user->assignRole(makeRole('syr'.uniqid(), ['access.permissions.sync']));

    return $user;
}

it('dry run returns the diff and writes nothing', function (): void {
    useRegistry('valid');
    $actor = syncer();
    ActionLog::query()->delete();
    $before = Permission::count();

    $this->actingAs($actor)->postJson('/api/access/permissions/sync', ['dry_run' => true])->assertOk()
        ->assertJsonPath('data.dry_run', true)->assertJsonPath('data.has_changes', true)
        ->assertJsonPath('data.added', ['orders.view', 'orders.update', 'orders.refund'])
        ->assertJsonStructure(['data' => ['added', 'updated', 'restored', 'deprecated', 'pruned', 'has_changes', 'dry_run']]);

    expect(Permission::count())->toBe($before)->and(ActionLog::count())->toBe(0);
});

it('syncs for real and audits once', function (): void {
    useRegistry('valid');
    $actor = syncer();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson('/api/access/permissions/sync')->assertOk()
        ->assertJsonPath('data.dry_run', false)->assertJsonPath('data.added', ['orders.view', 'orders.update', 'orders.refund']);

    $log = ActionLog::sole();
    expect($log->event)->toBe('permissions.synced')->and($log->actor_id)->toBe($actor->id)
        ->and(Permission::where('name', 'orders.refund')->value('source'))->toBe('registry');
});

it('prunes unheld deprecated permissions when asked', function (): void {
    useRegistry('valid');
    $p = makePermission('gone.perm');
    $p->forceFill(['source' => 'registry', 'deprecated_at' => now()])->save();

    $this->actingAs(syncer())->postJson('/api/access/permissions/sync', ['prune' => true])->assertOk()->assertJsonPath('data.pruned', ['gone.perm']);
    expect(Permission::where('name', 'gone.perm')->exists())->toBeFalse();
});

it('answers 422 registry.invalid for an invalid registry file with no change', function (): void {
    useRegistry('bad-risk');
    $actor = syncer();
    ActionLog::query()->delete();
    $before = Permission::count();

    $this->actingAs($actor)->postJson('/api/access/permissions/sync')->assertStatus(422)->assertJsonPath('code', 'registry.invalid');
    expect(Permission::count())->toBe($before)->and(ActionLog::count())->toBe(0);
});

it('validates flags and refuses without permission', function (): void {
    $this->postJson('/api/access/permissions/sync')->assertUnauthorized();
    $this->actingAs(syncer())->postJson('/api/access/permissions/sync', ['dry_run' => 'maybe'])->assertStatus(422)->assertJsonValidationErrorFor('dry_run', 'errors');
    $viewer = makeUser('vv@example.test');
    $viewer->assignRole(makeRole('vr', ['access.permissions.view']));
    $this->actingAs($viewer)->postJson('/api/access/permissions/sync')->assertForbidden();
});
