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

it('answers 409 when renaming onto a trashed role name and changes nothing', function (): void {
    makeRole('ghost')->delete();
    $role = makeRole('living');
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$role->id}", ['name' => 'ghost'])
        ->assertStatus(409)->assertJson(['code' => 'role.name_taken_trashed']);
    expect(Role::find($role->id)->name)->toBe('living')->and(ActionLog::count())->toBe(0);
});

it('replaces label maps wholesale', function (): void {
    $role = makeRole('maps', [], ['label' => ['en' => 'Maps', 'ar' => 'خرائط']]);
    $this->actingAs(roleWriter())->patchJson("/api/access/roles/{$role->id}", ['label' => ['en' => 'Maps 2']])
        ->assertOk()->assertJsonPath('data.label', ['en' => 'Maps 2']);
});

it('records which role lost the default flag', function (): void {
    $a = makeRole('first', [], ['is_default' => true]);
    $b = makeRole('second');
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$b->id}", ['is_default' => true])->assertOk();
    expect(ActionLog::sole()->meta['default_cleared_from'])->toBe([['id' => $a->id, 'name' => 'first']]);
});

it('refuses making the owner or a system role the default role', function (string $name, array $attributes): void {
    $role = makeRole($name, [], $attributes);
    $other = makeRole('keeper', [], ['is_default' => true]);
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$role->id}", ['is_default' => true])
        ->assertStatus(422)->assertJson(['code' => 'role.default_not_allowed']);

    expect($role->fresh()->is_default)->toBeFalse()->and($other->fresh()->is_default)->toBeTrue()->and(ActionLog::count())->toBe(0);
})->with([
    'owner role' => ['owner', ['is_system' => true]],
    'owner role without the flag' => ['owner', []],
    'other system role' => ['admin', ['is_system' => true]],
]);

it('allows clearing is_default on a system role and defaulting an ordinary one', function (): void {
    $system = makeRole('admin', [], ['is_system' => true, 'is_default' => true]);
    $plain = makeRole('plain');

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$system->id}", ['is_default' => false])->assertOk();
    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$plain->id}", ['is_default' => true])->assertOk();

    expect($system->fresh()->is_default)->toBeFalse()->and($plain->fresh()->is_default)->toBeTrue();
});

it('refuses creating a role named like the owner role as the default', function (): void {
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->postJson('/api/access/roles', ['name' => 'owner', 'label' => ['en' => 'Owner'], 'is_default' => true])
        ->assertStatus(422)->assertJson(['code' => 'role.default_not_allowed']);

    expect(Role::where('name', 'owner')->exists())->toBeFalse()->and(ActionLog::count())->toBe(0);
});

it('answers system_role, not a name conflict, when a system role is renamed onto a trashed name', function (): void {
    makeRole('spirit')->delete();
    $system = makeRole('admin', [], ['is_system' => true]);
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$system->id}", ['name' => 'spirit'])
        ->assertForbidden()->assertJson(['code' => 'guardrail.system_role']);

    expect($system->fresh()->name)->toBe('admin')->and(ActionLog::count())->toBe(0);
});
