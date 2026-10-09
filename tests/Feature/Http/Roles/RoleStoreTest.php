<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
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
    'permissions rejected' => [['name' => 'ok', 'label' => ['en' => 'x'], 'permissions' => ['a.b']], 'permissions'],
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
