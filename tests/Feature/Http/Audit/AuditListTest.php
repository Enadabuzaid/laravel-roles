<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\RolesServiceProvider;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;

function auditIds(array $json): array
{
    return array_column($json['data'], 'id');
}

it('lists entries newest first with the exact resource shape', function (): void {
    $owner = auditOwner();
    $target = makeUser('t@example.test');
    $old = makeAuditEntry(['event' => 'users.updated', 'created_at' => now()->subHour()]);
    $new = makeAuditEntry([
        'event' => 'users.suspended', 'severity' => 'warning', 'created_at' => now()->subMinute(),
        'actor_type' => $owner->getMorphClass(), 'actor_id' => $owner->id, 'actor_label' => 'Owner A',
        'subject_type' => $target->getMorphClass(), 'subject_id' => $target->id, 'subject_label' => 'Target T',
        'old_values' => ['status' => 'active'], 'new_values' => ['status' => 'suspended'],
        'meta' => ['reason' => 'Spam'], 'ip_address' => '10.0.0.1', 'request_id' => 'req-1',
    ]);

    $response = $this->actingAs($owner)->getJson('/api/access/audit')->assertOk()
        ->assertJsonPath('success', true)->assertJsonPath('meta.pagination.total', 2);

    expect(auditIds($response->json()))->toBe([$new->id, $old->id]);
    expect($response->json('data.0'))->toBe([
        'id' => $new->id,
        'event' => 'users.suspended',
        'module' => 'users',
        'severity' => 'warning',
        'created_at' => $new->created_at->toIso8601String(),
        'ip_address' => '10.0.0.1',
        'request_id' => 'req-1',
        'actor' => ['type' => 'user', 'id' => $owner->id, 'label' => 'Owner A', 'exists' => true],
        'subject' => ['type' => 'user', 'id' => $target->id, 'label' => 'Target T', 'exists' => true],
        'summary' => ['key' => 'access.audit.events.users.suspended', 'params' => ['actor' => 'Owner A', 'subject' => 'Target T', 'reason' => 'Spam']],
        'changes_count' => 1,
    ]);
});

it('filters by repeated module, severity, actor, subject, user, event', function (): void {
    $owner = auditOwner();
    $a = makeUser('a@example.test');
    $b = makeUser('b@example.test');
    $type = $a->getMorphClass();

    $e1 = makeAuditEntry(['module' => 'users', 'event' => 'users.suspended', 'severity' => 'critical', 'actor_type' => $type, 'actor_id' => $a->id, 'subject_type' => $type, 'subject_id' => $b->id]);
    $e2 = makeAuditEntry(['module' => 'roles', 'event' => 'roles.created', 'severity' => 'info', 'actor_type' => $type, 'actor_id' => $b->id]);
    $e3 = makeAuditEntry(['module' => 'permissions', 'event' => 'permissions.synced', 'severity' => 'warning']);

    $ids = fn (string $q): array => auditIds($this->actingAs($owner)->getJson('/api/access/audit?'.$q)->assertOk()->json());

    expect($ids('filter[module][]=users&filter[module][]=roles'))->toEqualCanonicalizing([$e1->id, $e2->id])
        ->and($ids('filter[module]=permissions'))->toBe([$e3->id])
        ->and($ids('filter[severity]=critical'))->toBe([$e1->id])
        ->and($ids('filter[actor]='.$a->id))->toBe([$e1->id])
        ->and($ids('filter[actor]=system'))->toBe([$e3->id])
        ->and($ids('filter[subject]=user:'.$b->id))->toBe([$e1->id])
        ->and($ids('filter[user]='.$b->id))->toEqualCanonicalizing([$e1->id, $e2->id])
        ->and($ids('filter[event]=roles.created'))->toBe([$e2->id]);
});

it('supports the range presets and inclusive from/to dates', function (): void {
    $this->travelTo(now()->setDate(2026, 6, 15)->setTime(12, 0));
    $owner = auditOwner();
    $hour = makeAuditEntry(['created_at' => now()->subHours(2)]);
    $day3 = makeAuditEntry(['created_at' => now()->subDays(3)]);
    $day20 = makeAuditEntry(['created_at' => now()->subDays(20)]);
    $day60 = makeAuditEntry(['created_at' => now()->subDays(60)]);

    $ids = fn (string $q): array => auditIds($this->actingAs($owner)->getJson('/api/access/audit?'.$q)->assertOk()->json());

    expect($ids('range=24h'))->toBe([$hour->id])
        ->and($ids('range=7d'))->toBe([$hour->id, $day3->id])
        ->and($ids('range=30d'))->toBe([$hour->id, $day3->id, $day20->id])
        ->and($ids(''))->toBe([$hour->id, $day3->id, $day20->id])
        ->and($ids('range=all'))->toBe([$hour->id, $day3->id, $day20->id, $day60->id]);

    $from = $day20->created_at->toDateString();
    $to = $day3->created_at->toDateString();
    expect($ids("from={$from}&to={$to}&range=24h"))->toBe([$day3->id, $day20->id])
        ->and($ids("from={$to}&to={$to}"))->toBe([$day3->id])
        ->and($ids('from='.$day60->created_at->toDateString().'&to='.$day60->created_at->toDateString()))->toBe([$day60->id]);
});

it('searches event, subject label and actor label with literal wildcards', function (): void {
    $owner = auditOwner();
    $byEvent = makeAuditEntry(['event' => 'roles.assigned', 'module' => 'roles']);
    $bySubject = makeAuditEntry(['subject_label' => 'Zed Zebra']);
    $byActor = makeAuditEntry(['actor_label' => 'Nadia Z']);
    $percent = makeAuditEntry(['subject_label' => '100% done']);
    makeAuditEntry(['subject_label' => '100 done']);
    $underscore = makeAuditEntry(['subject_label' => 'a_b']);
    makeAuditEntry(['subject_label' => 'axb']);

    $ids = fn (string $term): array => auditIds($this->actingAs($owner)->getJson('/api/access/audit?search='.urlencode($term))->assertOk()->json());

    expect($ids('roles.assigned'))->toBe([$byEvent->id])
        ->and($ids('zebra'))->toBe([$bySubject->id])
        ->and($ids('nadia'))->toBe([$byActor->id])
        ->and($ids('%'))->toBe([$percent->id])
        ->and($ids('a_b'))->toBe([$underscore->id]);
});

it('ignores unknown modules and garbage filter values without failing', function (): void {
    $owner = auditOwner();
    $one = makeAuditEntry();

    foreach ([
        'filter[module][]=../../', 'filter[module]=nope', 'filter[severity]=x', 'from=notadate', 'to=2026-13-45', 'range=forever',
        'filter[subject]=evil:1', 'filter[subject]=user:abc', 'filter[actor]=abc', 'filter[user]=-1', 'filter[event]=%27%3Bdrop',
        'filter=oops', 'filter[module][a][b]=1', 'per_page=abc', 'page=-5', 'search[]=x', 'sort=-evil',
    ] as $query) {
        $response = $this->actingAs($owner)->getJson('/api/access/audit?'.$query);
        expect($response->status())->toBe(200, $query);
        expect(auditIds($response->json()))->toBe([$one->id], $query);
    }
});

it('clamps per_page to the configured maximum', function (): void {
    $owner = auditOwner();
    makeAuditEntry();

    $this->actingAs($owner)->getJson('/api/access/audit?per_page=10000')->assertOk()
        ->assertJsonPath('meta.pagination.per_page', (int) config('roles.pagination.max'));
});

it('renders deleted subjects and actors, pre-1.2 rows and system actors', function (): void {
    $owner = auditOwner();
    $gone = makeUser('gone@example.test');
    $goneId = $gone->id;
    $type = $gone->getMorphClass();
    $deleted = makeAuditEntry(['event' => 'users.suspended', 'subject_type' => $type, 'subject_id' => $goneId, 'subject_label' => 'Gone G', 'actor_type' => $type, 'actor_id' => $goneId, 'actor_label' => 'Gone G']);
    $legacy = makeAuditEntry(['event' => 'roles.created', 'subject_type' => $type, 'subject_id' => $owner->id, 'actor_type' => $type, 'actor_id' => $owner->id]);
    $system = makeAuditEntry(['event' => 'permissions.synced']);
    DB::table('users')->where('id', $goneId)->delete();

    $rows = collect($this->actingAs($owner)->getJson('/api/access/audit')->assertOk()->json('data'))->keyBy('id');

    expect($rows[$deleted->id]['subject'])->toBe(['type' => 'user', 'id' => $goneId, 'label' => 'Gone G', 'exists' => false])
        ->and($rows[$deleted->id]['actor']['exists'])->toBeFalse()
        ->and($rows[$legacy->id]['subject'])->toBe(['type' => 'user', 'id' => $owner->id, 'label' => null, 'exists' => true])
        ->and($rows[$legacy->id]['actor']['label'])->toBeNull()
        ->and($rows[$legacy->id]['summary']['params']['subject'])->toBe('#'.$owner->id)
        ->and($rows[$system->id]['actor'])->toBe(['type' => 'system', 'id' => null, 'label' => null, 'exists' => true])
        ->and($rows[$system->id]['subject'])->toBe(['type' => null, 'id' => null, 'label' => null, 'exists' => false]);
});

it('maps role, permission and invitation subjects and unknown events', function (): void {
    $owner = auditOwner();
    $role = makeRole('editor');
    $permission = Permission::findOrCreate('access.something', 'web');
    $invitation = AccessInvitation::create(['email' => 'i@example.test', 'token_hash' => 'x', 'expires_at' => now()->addDay()]);

    $r = makeAuditEntry(['event' => 'roles.updated', 'subject_type' => $role->getMorphClass(), 'subject_id' => $role->id]);
    $p = makeAuditEntry(['event' => 'permissions.updated', 'subject_type' => $permission->getMorphClass(), 'subject_id' => $permission->id]);
    $i = makeAuditEntry(['event' => 'invitations.created', 'subject_type' => $invitation->getMorphClass(), 'subject_id' => $invitation->id]);
    $x = makeAuditEntry(['event' => 'billing.invoiced', 'subject_type' => 'App\\Models\\Nope', 'subject_id' => 5]);

    $rows = collect($this->actingAs($owner)->getJson('/api/access/audit')->json('data'))->keyBy('id');

    expect($rows[$r->id]['subject']['type'])->toBe('role')->and($rows[$r->id]['subject']['exists'])->toBeTrue()
        ->and($rows[$p->id]['subject']['type'])->toBe('permission')
        ->and($rows[$i->id]['subject']['type'])->toBe('invitation')->and($rows[$i->id]['subject']['exists'])->toBeTrue()
        ->and($rows[$x->id]['subject'])->toMatchArray(['type' => 'other', 'exists' => false])
        ->and($rows[$x->id]['summary']['key'])->toBe('access.audit.events.generic')
        ->and($rows[$x->id]['summary']['params']['event'])->toBe('billing.invoiced');
});

it('uses a fixed number of queries for 5 and 500 entries with mixed actor and subject types', function (): void {
    $owner = auditOwner();
    $user = makeUser('u@example.test');
    $role = makeRole('r1');
    $permission = Permission::findOrCreate('access.p', 'web');

    $seed = function (int $n) use ($user, $role, $permission): void {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $subject = [$user, $role, $permission][$i % 3];
            $rows[] = [
                'event' => 'roles.updated', 'module' => 'roles', 'severity' => 'info', 'created_at' => now(), 'updated_at' => now(),
                'actor_type' => $user->getMorphClass(), 'actor_id' => $user->id,
                'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('action_logs')->insert($chunk);
        }
    };

    Model::preventLazyLoading();
    $count = function () use ($owner): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($owner->fresh())->getJson('/api/access/audit?per_page=100')->assertOk();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    $seed(5);
    $count();
    $few = $count();
    $seed(495);
    $many = $count();

    Model::preventLazyLoading(false);

    expect($many)->toBe($few);
});

it('answers 401 and 403 with the envelope', function (): void {
    $this->getJson('/api/access/audit')->assertUnauthorized()->assertJson(['success' => false, 'code' => 'unauthenticated']);

    $user = auditActor(['access.users.view']);
    foreach (['audit', 'audit/overview', 'audit/actors', 'audit/1', 'audit/export'] as $path) {
        $this->actingAs($user)->getJson('/api/access/'.$path)->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);
    }
});

it('lets a user with only access.audit.view read the log', function (): void {
    makeAuditEntry();

    $this->actingAs(auditActor())->getJson('/api/access/audit')->assertOk()->assertJsonPath('meta.pagination.total', 1);
});

it('has no audit routes when the audit module is disabled', function (): void {
    config(['roles.modules.audit' => false]);
    app('router')->setRoutes(new RouteCollection);
    (new RolesServiceProvider(app()))->boot();

    $owner = auditOwner();
    $this->actingAs($owner)->getJson('/api/access/audit')->assertNotFound();
    $this->actingAs($owner)->getJson('/api/access/audit/overview')->assertNotFound();
    $this->actingAs($owner)->getJson('/api/access/roles')->assertOk();
});

it('exposes only GET routes under audit (read-only over HTTP)', function (): void {
    $audit = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/access/audit'));

    expect($audit)->not->toBeEmpty();
    foreach ($audit as $route) {
        expect(array_values(array_diff($route->methods(), ['HEAD'])))->toBe(['GET'], $route->uri());
    }
});

it('never lists secrets from rows written before redaction existed', function (): void {
    $owner = auditOwner();
    makeAuditEntry(['old_values' => ['password' => 'hunter2'], 'new_values' => ['password' => 'hunter3', 'name' => 'x'], 'meta' => ['token_hash' => 'abc']]);

    $body = $this->actingAs($owner)->getJson('/api/access/audit')->assertOk()->getContent();

    expect($body)->not->toContain('hunter')->not->toContain('abc');
});
