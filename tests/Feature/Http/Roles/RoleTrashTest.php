<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestUser;

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
    expect(ActionLog::count())->toBe(0)->and(Role::withTrashed()->find($role->id)->trashed())->toBeFalse();

    $this->actingAs($actor)->deleteJson("/api/access/roles/{$role->id}", ['confirmation' => 'crew'])->assertOk();

    $log = ActionLog::sole();
    expect($log->severity)->toBe('warning')->and($log->meta['members_count'])->toBe(1);
    expect($member->fresh()->can('reports.view'))->toBeFalse();
});

it('refuses trashing system and owner roles and leaves them live', function (): void {
    $system = makeRole('sys', [], ['is_system' => true]);
    $owner = makeRole('owner');
    ActionLog::query()->delete();

    foreach ([$system, $owner] as $role) {
        $this->actingAs(roleWriter())->deleteJson("/api/access/roles/{$role->id}")->assertForbidden()->assertJson(['code' => 'guardrail.system_role']);
        expect(Role::withTrashed()->find($role->id)->trashed())->toBeFalse();
    }
    expect(ActionLog::count())->toBe(0);
});

it('refuses renaming the owner role and force deleting a trashed owner role', function (): void {
    $owner = makeRole('owner');
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->putJson("/api/access/roles/{$owner->id}", ['name' => 'boss'])->assertForbidden()->assertJson(['code' => 'guardrail.system_role']);
    expect(Role::find($owner->id)->name)->toBe('owner')->and(ActionLog::count())->toBe(0);

    $owner->delete();
    $this->actingAs(roleWriter())->deleteJson("/api/access/roles/{$owner->id}/force", ['confirmation' => 'owner'])->assertForbidden()->assertJson(['code' => 'guardrail.system_role']);
    expect(Role::withTrashed()->find($owner->id)->trashed())->toBeTrue()->and(ActionLog::count())->toBe(0);
});

it('restores a trashed role', function (): void {
    $role = makeRole('back');
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->postJson("/api/access/roles/{$role->id}/restore")->assertOk()->assertJsonPath('data.name', 'back');
    expect(ActionLog::sole()->event)->toBe('roles.restored')->and(Role::find($role->id))->not->toBeNull();
});

it('answers 409 for restore of a live role and 404 for a missing one', function (): void {
    $live = makeRole('live');
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
    expect(ActionLog::count())->toBe(0)->and(Role::withTrashed()->find($role->id)->trashed())->toBeTrue();

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

it('removes pivot rows of every model type on force delete', function (): void {
    $role = makeRole('pivoted');
    $table = config('permission.table_names.model_has_roles');
    DB::table($table)->insert([
        ['role_id' => $role->id, 'model_type' => 'App\\Other', 'model_id' => 5],
        ['role_id' => $role->id, 'model_type' => TestUser::class, 'model_id' => 99],
    ]);
    $role->delete();

    $this->actingAs(roleWriter())->deleteJson("/api/access/roles/{$role->id}/force", ['confirmation' => 'pivoted'])->assertNoContent();
    expect(DB::table($table)->where('role_id', $role->id)->count())->toBe(0);
});

it('refuses a non-owner restoring a role they hold and leaves it trashed', function (): void {
    $role = makeRole('mine', ['reports.view']);
    $actor = roleWriter();
    $actor->assignRole($role);
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/roles/{$role->id}/restore")->assertForbidden()->assertJson(['code' => 'guardrail.self_role_change']);
    expect(Role::withTrashed()->find($role->id)->trashed())->toBeTrue()->and(ActionLog::count())->toBe(0);
});

it('refuses restoring a role with permissions the actor lacks', function (): void {
    $role = makeRole('wide', ['secret.view']);
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->postJson("/api/access/roles/{$role->id}/restore")->assertForbidden()->assertJson(['code' => 'guardrail.escalation']);
    expect(Role::withTrashed()->find($role->id)->trashed())->toBeTrue()->and(ActionLog::count())->toBe(0);
});

it('requires confirm to restore a high-risk role, then restores it with one audit entry', function (): void {
    makePermission('danger.run', 'high');
    $actor = roleWriter();
    $actor->givePermissionTo('danger.run');
    $role = makeRole('risky', ['danger.run']);
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/roles/{$role->id}/restore")->assertForbidden()->assertJson(['code' => 'guardrail.high_risk_confirm_required']);
    expect(Role::withTrashed()->find($role->id)->trashed())->toBeTrue()->and(ActionLog::count())->toBe(0);

    $this->actingAs($actor)->postJson("/api/access/roles/{$role->id}/restore", ['confirm' => true])->assertOk();
    expect(ActionLog::sole()->event)->toBe('roles.restored')->and(Role::find($role->id))->not->toBeNull();
});

it('refuses a non-owner restoring the owner role', function (): void {
    makeOwner('boss@example.test');
    $role = Role::where('name', 'owner')->first();
    $role->delete();
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->postJson("/api/access/roles/{$role->id}/restore", ['confirm' => true])->assertForbidden()->assertJson(['code' => 'guardrail.escalation']);
    expect(Role::withTrashed()->find($role->id)->trashed())->toBeTrue()->and(ActionLog::count())->toBe(0);
});
