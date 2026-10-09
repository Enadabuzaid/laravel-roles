<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Role;

it('updates changed attributes and audits before/after once', function (): void {
    $role = makeRole('editor', [], ['label' => ['en' => 'Editor'], 'color' => '#000000']);
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$role->id}", ['color' => '#111111', 'label' => ['en' => 'Editor', 'ar' => 'محرر'], 'is_system' => true])
        ->assertOk()->assertJsonPath('data.color', '#111111')->assertJsonPath('data.is_system', false);

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.updated')->and($log->old_values['color'])->toBe('#000000')->and($log->new_values['color'])->toBe('#111111');
});

it('is a no-op without audit or updated_at change', function (): void {
    $role = makeRole('same', [], ['label' => ['en' => 'Same'], 'color' => '#000000']);
    $role->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $before = Role::find($role->id)->updated_at;
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->patchJson("/api/access/roles/{$role->id}", ['color' => '#000000', 'name' => 'same'])->assertOk();

    expect(ActionLog::count())->toBe(0)->and(Role::find($role->id)->updated_at->equalTo($before))->toBeTrue();
});

it('renames a custom role', function (): void {
    $role = makeRole('old-name');
    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$role->id}", ['name' => 'new-name'])->assertOk()->assertJsonPath('data.name', 'new-name');
});

it('refuses renaming a system role with no change and no audit', function (): void {
    $role = makeRole('core', [], ['is_system' => true]);
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$role->id}", ['name' => 'core2'])
        ->assertForbidden()->assertJson(['code' => 'guardrail.system_role']);
    expect(Role::find($role->id)->name)->toBe('core')->and(ActionLog::count())->toBe(0);
});

it('clears is_default on other roles', function (): void {
    $a = makeRole('a', [], ['is_default' => true]);
    $b = makeRole('b');
    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$b->id}", ['is_default' => true])->assertOk();
    expect($a->fresh()->is_default)->toBeFalse()->and($b->fresh()->is_default)->toBeTrue();
});

it('answers 404 for trashed and missing roles, 403 without permission, 422 on bad input', function (): void {
    $trashed = makeRole('t');
    $trashed->delete();
    $live = makeRole('l');
    $actor = roleWriter();

    $this->actingAs($actor)->putJson("/api/access/roles/{$trashed->id}", ['color' => '#111111'])->assertNotFound()->assertJson(['code' => 'not_found']);
    $this->actingAs($actor)->putJson('/api/access/roles/99999', [])->assertNotFound();
    $this->actingAs($actor)->putJson('/api/access/roles/abc', [])->assertNotFound();
    $this->actingAs($actor)->putJson("/api/access/roles/{$live->id}", ['name' => 'Bad Name'])->assertStatus(422);
    $this->actingAs(roleWriter(['access.roles.view']))->putJson("/api/access/roles/{$live->id}", ['color' => '#111111'])->assertForbidden();
});

it('refuses a rename onto an existing name', function (): void {
    makeRole('taken');
    $role = makeRole('mine');
    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$role->id}", ['name' => 'taken'])->assertStatus(422);
});
