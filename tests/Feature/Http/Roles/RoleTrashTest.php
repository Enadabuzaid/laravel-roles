<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Role;

it('trashes a memberless role without confirmation', function (): void {
    $role = makeRole('empty');
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->deleteJson("/api/access/roles/{$role->id}")->assertOk()->assertJsonPath('data.name', 'empty');

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.trashed')->and($log->severity)->toBe('info')->and($log->meta['members_count'])->toBe(0);
    expect(Role::find($role->id))->toBeNull();
});

it('requires type-to-confirm for a role with members and drops their access', function (): void {
    $role = makeRole('crew', ['reports.view']);
    $member = makeUser('m@example.test');
    $member->assignRole($role);
    expect($member->fresh()->can('reports.view'))->toBeTrue();
    $actor = roleWriter();
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}")->assertForbidden()->assertJson(['code' => 'guardrail.confirmation_mismatch']);
    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}", ['confirmation' => 'nope'])->assertForbidden()->assertJson(['code' => 'guardrail.confirmation_mismatch']);
    expect(ActionLog::count())->toBe(0);

    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}", ['confirmation' => 'crew'])->assertOk();

    $log = ActionLog::sole();
    expect($log->severity)->toBe('warning')->and($log->meta['members_count'])->toBe(1);
    expect($member->fresh()->can('reports.view'))->toBeFalse();
});

it('refuses trashing system and owner roles', function (): void {
    $system = makeRole('sys', [], ['is_system' => true]);
    $owner = makeRole('owner');
    ActionLog::query()->delete();

    foreach ([$system, $owner] as $role) {
        $this->actingAs(roleWriter())->deleteJson("/api/access/roles/{$role->id}")->assertForbidden()->assertJson(['code' => 'guardrail.system_role']);
    }
    expect(ActionLog::count())->toBe(0)->and(Role::count())->toBeGreaterThanOrEqual(2);
});

it('restores a trashed role', function (): void {
    $role = makeRole('back');
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->postJson("/api/access/roles/{$role->id}/restore")->assertOk()->assertJsonPath('data.name', 'back');
    expect(ActionLog::sole()->event)->toBe('roles.restored')->and(Role::find($role->id))->not->toBeNull();
});

it('answers 409 for restore of a live role and when the name is taken', function (): void {
    $live = makeRole('live');
    $trashed = makeRole('dupe');
    $trashed->delete();
    // simulate a live role now holding the name (unique index includes trashed rows, so rename the trashed one first)
    Role::withTrashed()->whereKey($trashed->id)->update(['name' => 'dupe']);
    $actor = roleWriter();

    $this->actingAs($actor)->postJson("/api/access/roles/{$live->id}/restore")->assertStatus(409)->assertJson(['code' => 'role.not_trashed']);
    $this->actingAs($actor)->postJson('/api/access/roles/99999/restore')->assertNotFound();
});

it('force deletes only trashed roles with the exact name', function (): void {
    $role = makeRole('doomed', ['reports.view']);
    $actor = roleWriter();

    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}/force", ['confirmation' => 'doomed'])->assertStatus(409)->assertJson(['code' => 'role.not_trashed']);
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}/force", ['confirmation' => 'wrong'])->assertForbidden()->assertJson(['code' => 'guardrail.confirmation_mismatch']);
    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}/force")->assertForbidden();
    expect(ActionLog::count())->toBe(0);

    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}/force", ['confirmation' => 'doomed'])->assertNoContent();

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.deleted')->and($log->severity)->toBe('critical')->and($log->subject_id)->toBeNull()
        ->and($log->old_values['name'])->toBe('doomed')->and($log->meta['name'])->toBe('doomed');
    expect(Role::withTrashed()->find($role->id))->toBeNull();
});

it('refuses force deleting a system role and requires delete permission', function (): void {
    $role = makeRole('sys2', [], ['is_system' => true]);
    $role->delete();
    $this->actingAs(roleWriter())->deleteJson("/api/access/roles/{$role->id}/force", ['confirmation' => 'sys2'])->assertForbidden()->assertJson(['code' => 'guardrail.system_role']);
    $this->actingAs(roleWriter(['access.roles.view']))->postJson("/api/access/roles/{$role->id}/restore")->assertForbidden();
});
