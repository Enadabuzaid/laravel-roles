<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Tests\Support\TestUser;

function roleWriter(array $permissions = ['access.roles.create', 'access.roles.update', 'access.roles.delete']): TestUser
{
    $user = makeUser(uniqid().'@example.test');
    $user->assignRole(makeRole('writer'.uniqid(), $permissions));

    return $user;
}

it('creates a role and audits it once', function (): void {
    $actor = roleWriter();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson('/api/access/roles', [
        'name' => 'support-agent',
        'label' => ['en' => 'Support agent', 'ar' => 'وكيل الدعم'],
        'description' => ['en' => 'Helps'],
        'color' => '#aabbcc',
        'is_default' => true,
        'sort_order' => 5,
        'is_system' => true,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'support-agent')
        ->assertJsonPath('data.is_system', false)
        ->assertJsonPath('data.label.ar', 'وكيل الدعم')
        ->assertJsonPath('data.abilities.update', true);

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.created')->and($log->module)->toBe('roles')->and($log->severity)->toBe('info')
        ->and($log->actor_id)->toBe($actor->id);
    expect(Role::where('name', 'support-agent')->value('is_system'))->toBeFalse();
});

it('defaults sort_order to max plus one and returns the arabic label text', function (): void {
    makeRole('a', [], ['sort_order' => 7]);
    $this->actingAs(roleWriter())->postJson('/api/access/roles', ['name' => 'bb', 'label' => ['en' => 'B', 'ar' => 'ب']])
        ->assertCreated()->assertJsonPath('data.sort_order', 8);

    app()->setLocale('ar');
    expect(Role::where('name', 'bb')->first()->label_text)->toBe('ب');
});

it('refuses without permission', function (): void {
    ActionLog::query()->delete();
    $this->actingAs(roleWriter(['access.roles.view']))->postJson('/api/access/roles', ['name' => 'x', 'label' => ['en' => 'X']])
        ->assertForbidden();
    expect(ActionLog::count())->toBe(0);
});

it('validates input', function (array $body, string $field): void {
    ActionLog::query()->delete();
    $this->actingAs(roleWriter())->postJson('/api/access/roles', $body)
        ->assertStatus(422)->assertJson(['success' => false, 'code' => 'validation_failed'])->assertJsonValidationErrorFor($field, 'errors');
    expect(ActionLog::count())->toBe(0);
})->with([
    'missing default label' => [['name' => 'ok', 'label' => ['ar' => 'x']], 'label.en'],
    'bad slug' => [['name' => 'Bad Name', 'label' => ['en' => 'x']], 'name'],
    'locale not configured' => [['name' => 'ok', 'label' => ['en' => 'x', 'fr' => 'y']], 'label'],
    'bad color' => [['name' => 'ok', 'label' => ['en' => 'x'], 'color' => 'red'], 'color'],
    'permissions not a list' => [['name' => 'ok', 'label' => ['en' => 'x'], 'permissions' => 'a.b'], 'permissions'],
    'permission entry invalid' => [['name' => 'ok', 'label' => ['en' => 'x'], 'permissions' => [['a']]], 'permissions'],
    'too many permissions' => [['name' => 'ok', 'label' => ['en' => 'x'], 'permissions' => range(1, 501)], 'permissions'],
]);

it('rejects a duplicate name', function (): void {
    makeRole('dup');
    $this->actingAs(roleWriter())->postJson('/api/access/roles', ['name' => 'dup', 'label' => ['en' => 'x']])
        ->assertStatus(422)->assertJsonValidationErrorFor('name', 'errors');
});

it('answers 409 when a trashed role holds the name', function (): void {
    makeRole('gone')->delete();
    ActionLog::query()->delete();

    $this->actingAs(roleWriter())->postJson('/api/access/roles', ['name' => 'gone', 'label' => ['en' => 'x']])
        ->assertStatus(409)->assertJson(['success' => false, 'code' => 'role.name_taken_trashed']);
    expect(ActionLog::count())->toBe(0);
});

it('creates a role with permissions by name and id and audits once with the granted names', function (): void {
    $actor = roleWriter();
    $actor->givePermissionTo([makePermission('a.view'), makePermission('b.view')]);
    $b = Permission::where('name', 'b.view')->first();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson('/api/access/roles', ['name' => 'with-perms', 'label' => ['en' => 'W'], 'permissions' => ['a.view', $b->id]])
        ->assertCreated()->assertJsonPath('data.permissions_count', 2);

    $role = Role::where('name', 'with-perms')->first();
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['a.view', 'b.view']);
    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.created')->and($log->meta['permissions'])->toBe(['a.view', 'b.view']);
});

it('still creates a role without permissions', function (): void {
    ActionLog::query()->delete();
    $this->actingAs(roleWriter())->postJson('/api/access/roles', ['name' => 'bare', 'label' => ['en' => 'B'], 'permissions' => []])->assertCreated();
    expect(ActionLog::sole()->meta['permissions'] ?? [])->toBe([]);
});

it('leaves no role and no audit entry when a permission grant is refused', function (string $code, array $body, int $status): void {
    $actor = roleWriter();
    $actor->givePermissionTo(makePermission('held.view'));
    makePermission('lacking.view');
    makePermission('danger.run', 'high');
    $actor->givePermissionTo('danger.run');
    Permission::create(['name' => 'old.view', 'guard_name' => 'web'])->forceFill(['deprecated_at' => now()])->save();
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson('/api/access/roles', ['name' => 'atomic', 'label' => ['en' => 'A'], ...$body])
        ->assertStatus($status)->assertJson(['code' => $code]);

    expect(Role::withTrashed()->where('name', 'atomic')->exists())->toBeFalse()->and(ActionLog::count())->toBe(0);
})->with([
    'escalation' => ['guardrail.escalation', ['permissions' => ['held.view', 'lacking.view']], 403],
    'high risk without confirm' => ['guardrail.high_risk_confirm_required', ['permissions' => ['danger.run']], 403],
    'deprecated' => ['permission.deprecated', ['permissions' => ['old.view']], 409],
    'unknown' => ['permission.not_found', ['permissions' => ['held.view', 'nope.view']], 404],
]);

it('creates a high-risk role with confirm and records a warning', function (): void {
    $actor = roleWriter();
    makePermission('danger.run', 'high');
    $actor->givePermissionTo('danger.run');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson('/api/access/roles', ['name' => 'risky', 'label' => ['en' => 'R'], 'permissions' => ['danger.run'], 'confirm' => true])->assertCreated();
    expect(ActionLog::sole()->severity)->toBe('warning');
});

it('lets an owner create a role with any permission', function (): void {
    $owner = makeOwner();
    makePermission('lacking.view');

    $this->actingAs($owner)->postJson('/api/access/roles', ['name' => 'by-owner', 'label' => ['en' => 'O'], 'permissions' => ['lacking.view']])->assertCreated();
    expect(Role::where('name', 'by-owner')->first()->permissions->pluck('name')->all())->toBe(['lacking.view']);
});
