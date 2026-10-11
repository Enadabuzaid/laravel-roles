<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

function seedOverview(): array
{
    $a = makeUser('a@example.test');
    $b = makeUser('b@example.test');
    $type = $a->getMorphClass();

    makeAuditEntry(['module' => 'users', 'severity' => 'info', 'actor_type' => $type, 'actor_id' => $a->id, 'actor_label' => 'Alice']);
    makeAuditEntry(['module' => 'users', 'severity' => 'critical', 'actor_type' => $type, 'actor_id' => $a->id, 'actor_label' => 'Alice']);
    makeAuditEntry(['module' => 'roles', 'severity' => 'warning', 'actor_type' => $type, 'actor_id' => $b->id, 'actor_label' => 'Bob']);
    makeAuditEntry(['module' => 'permissions', 'severity' => 'warning']);
    makeAuditEntry(['module' => 'users', 'severity' => 'info', 'created_at' => now()->subDays(90)]);

    return [$a, $b];
}

it('summarizes the default 30 day window', function (): void {
    $owner = auditOwner();
    seedOverview();

    $data = $this->actingAs($owner)->getJson('/api/access/audit/overview')->assertOk()->json('data');

    expect($data)->toBe([
        'total' => 4,
        'by_severity' => ['info' => 1, 'warning' => 2, 'critical' => 1],
        'by_module' => ['users' => 2, 'invitations' => 0, 'roles' => 1, 'permissions' => 1, 'auth' => 0, 'reports' => 0, 'audit' => 0],
        'actors' => 3,
    ]);
});

it('applies the filters except severity', function (): void {
    $owner = auditOwner();
    [$a] = seedOverview();

    $data = $this->actingAs($owner)->getJson('/api/access/audit/overview?filter[module]=users&filter[severity]=critical')->assertOk()->json('data');
    expect($data['total'])->toBe(2)->and($data['by_severity'])->toBe(['info' => 1, 'warning' => 0, 'critical' => 1])->and($data['actors'])->toBe(1);

    $all = $this->actingAs($owner)->getJson('/api/access/audit/overview?range=all')->json('data');
    expect($all['total'])->toBe(5);

    $actor = $this->actingAs($owner)->getJson('/api/access/audit/overview?filter[actor]='.$a->id)->json('data');
    expect($actor['total'])->toBe(2);
});

it('runs one aggregate query', function (): void {
    $owner = auditOwner();
    seedOverview();
    $this->actingAs($owner)->getJson('/api/access/audit/overview')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($owner->fresh())->getJson('/api/access/audit/overview')->assertOk();
    $audit = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'action_logs'));
    DB::disableQueryLog();

    expect($audit)->toHaveCount(1);
});

it('lists the actors in range, most active first, system included', function (): void {
    $owner = auditOwner();
    [$a, $b] = seedOverview();

    $data = $this->actingAs($owner)->getJson('/api/access/audit/actors')->assertOk()->json('data');

    expect($data)->toBe([
        ['id' => $a->id, 'type' => 'user', 'label' => 'Alice', 'count' => 2],
        ['id' => $b->id, 'type' => 'user', 'label' => 'Bob', 'count' => 1],
        ['id' => null, 'type' => 'system', 'label' => 'System', 'count' => 1],
    ]);
});

it('falls back to #id for actors written before labels and collapses renamed actors', function (): void {
    $owner = auditOwner();
    $a = makeUser('a@example.test');
    $type = $a->getMorphClass();
    makeAuditEntry(['actor_type' => $type, 'actor_id' => $a->id]);
    makeAuditEntry(['actor_type' => $type, 'actor_id' => $a->id, 'actor_label' => 'Old name']);
    makeAuditEntry(['actor_type' => $type, 'actor_id' => $a->id, 'actor_label' => 'New name']);

    $data = $this->actingAs($owner)->getJson('/api/access/audit/actors')->assertOk()->json('data');

    expect($data)->toHaveCount(1)->and($data[0]['count'])->toBe(3)->and($data[0]['label'])->toBeIn(['Old name', 'New name']);
});

it('requires access.audit.view for overview and actors', function (): void {
    $user = auditActor(['access.users.view']);

    $this->actingAs($user)->getJson('/api/access/audit/overview')->assertForbidden();
    $this->actingAs($user)->getJson('/api/access/audit/actors')->assertForbidden();
});
