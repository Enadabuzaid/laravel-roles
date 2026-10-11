<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\Support\ChangeSet;
use Enadstack\LaravelRoles\Models\Role;

it('returns the list fields plus changes, meta and user agent', function (): void {
    $owner = auditOwner();
    $entry = makeAuditEntry([
        'event' => 'users.suspended', 'severity' => 'warning', 'actor_type' => $owner->getMorphClass(), 'actor_id' => $owner->id,
        'old_values' => ['status' => 'active'], 'new_values' => ['status' => 'suspended'],
        'meta' => ['reason' => 'Spam'], 'user_agent' => 'Mozilla/5.0',
    ]);

    $data = $this->actingAs($owner)->getJson('/api/access/audit/'.$entry->id)->assertOk()->json('data');

    expect($data['id'])->toBe($entry->id)
        ->and($data['changes'])->toBe([['field' => 'status', 'from' => 'active', 'to' => 'suspended', 'kind' => 'changed']])
        ->and($data['meta'])->toBe(['reason' => 'Spam'])
        ->and($data['user_agent'])->toBe('Mozilla/5.0')
        ->and($data['changes_count'])->toBe(1)
        ->and($data['actor']['exists'])->toBeTrue();
});

it('diffs a role permission sync as added and removed sets', function (): void {
    $owner = auditOwner();
    $entry = makeAuditEntry([
        'event' => 'roles.permissions_updated', 'module' => 'roles',
        'old_values' => ['permissions' => ['a.view', 'a.edit']],
        'new_values' => ['permissions' => ['a.edit', 'a.delete', 'a.export']],
    ]);

    $changes = $this->actingAs($owner)->getJson('/api/access/audit/'.$entry->id)->assertOk()->json('data.changes');

    expect($changes)->toBe([['field' => 'permissions', 'from' => ['a.view'], 'to' => ['a.delete', 'a.export'], 'kind' => 'changed']]);
});

it('redacts secrets at any depth in changes and meta', function (): void {
    $owner = auditOwner();
    $entry = makeAuditEntry([
        'old_values' => ['password' => 'old-pw', 'name' => 'A'],
        'new_values' => ['password' => 'new-pw', 'name' => 'B', 'profile' => ['two_factor_secret' => 'SECRET', 'bio' => 'x']],
        'meta' => ['nested' => ['two_factor_secret' => 'SECRET2', 'ok' => 1], 'Token_Hash' => 'abc'],
    ]);

    $response = $this->actingAs($owner)->getJson('/api/access/audit/'.$entry->id)->assertOk();
    $body = $response->getContent();

    expect($body)->not->toContain('old-pw')->not->toContain('new-pw')->not->toContain('SECRET')->not->toContain('abc')
        ->and($response->json('data.meta.nested.two_factor_secret'))->toBe('[redacted]')
        ->and($response->json('data.meta.Token_Hash'))->toBe('[redacted]');

    $byField = collect($response->json('data.changes'))->keyBy('field');
    expect($byField['password']['from'])->toBe('[redacted]')->and($byField['password']['to'])->toBe('[redacted]');
});

it('honours roles.audit.redact over the action logger list', function (): void {
    config(['roles.audit.redact' => ['bio']]);
    $owner = auditOwner();
    $entry = makeAuditEntry(['new_values' => ['bio' => 'private', 'password' => 'visible-here']]);

    $body = $this->actingAs($owner)->getJson('/api/access/audit/'.$entry->id)->assertOk()->getContent();

    expect($body)->not->toContain('private')->toContain('visible-here');
});

it('answers 404 for an unknown or non numeric entry', function (): void {
    $owner = auditOwner();

    $this->actingAs($owner)->getJson('/api/access/audit/999999')->assertNotFound()->assertJson(['code' => 'not_found']);
    $this->actingAs($owner)->getJson('/api/access/audit/abc')->assertNotFound();
});

it('computes change sets', function (): void {
    expect(ChangeSet::between(null, null))->toBe([])
        ->and(ChangeSet::between(['a' => 1], ['a' => 1]))->toBe([])
        ->and(ChangeSet::between(['b' => 1, 'a' => 1], ['a' => 2, 'c' => 3]))->toBe([
            ['field' => 'a', 'from' => 1, 'to' => 2, 'kind' => 'changed'],
            ['field' => 'b', 'from' => 1, 'to' => null, 'kind' => 'removed'],
            ['field' => 'c', 'from' => null, 'to' => 3, 'kind' => 'added'],
        ])
        ->and(ChangeSet::between(null, ['roles' => ['x']]))->toBe([['field' => 'roles', 'from' => [], 'to' => ['x'], 'kind' => 'added']])
        ->and(ChangeSet::between(['label' => ['en' => 'A']], ['label' => ['en' => 'B']]))->toBe([['field' => 'label', 'from' => ['en' => 'A'], 'to' => ['en' => 'B'], 'kind' => 'changed']])
        ->and(ChangeSet::between(['roles' => ['x', 'y']], ['roles' => ['y', 'x']]))->toBe([]);
});

it('records a real role update with a diff the detail endpoint returns', function (): void {
    $owner = auditOwner();
    $role = makeRole('editor', ['access.roles.view']);
    ActionLog::query()->delete();

    $this->actingAs($owner)->patchJson('/api/access/roles/'.$role->id, ['label' => ['en' => 'Editors']])->assertOk();

    $entry = ActionLog::query()->where('event', 'roles.updated')->sole();
    expect($entry->subject_label)->not->toBeNull();
    $changes = $this->actingAs($owner)->getJson('/api/access/audit/'.$entry->id)->assertOk()->json('data.changes');
    expect(array_column($changes, 'field'))->toContain('label');
    expect(Role::query()->find($role->id))->not->toBeNull();
});
