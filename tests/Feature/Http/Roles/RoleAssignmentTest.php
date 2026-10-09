<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestUser;

/**
 * @param  list<string>  $permissions
 */
function assigner(array $permissions = []): TestUser
{
    $user = makeUser(uniqid().'@example.test');
    $user->assignRole(makeRole('assigner'.uniqid(), ['access.roles.assign', ...$permissions]));

    return $user;
}

it('assigns a role by name or id and audits once as the authenticated actor', function (): void {
    $actor = assigner(['access.users.view']);
    $target = makeUser('t@example.test');
    $role = makeRole('editor', [], ['label' => ['en' => 'Editor'], 'color' => '#112233']);
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/roles", ['role' => 'editor'])
        ->assertOk()
        ->assertJsonPath('data.user', ['id' => $target->id, 'name' => 'A', 'email' => 't@example.test'])
        ->assertJsonPath('data.roles.0.name', 'editor')
        ->assertJsonPath('data.roles.0.color', '#112233')
        ->assertJsonPath('data.roles.0.is_system', false)
        ->assertJsonPath('data.roles.0.label_text', 'Editor');

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.assigned')->and((int) $log->actor_id)->toBe($actor->id);

    $other = makeUser('u@example.test');
    $this->actingAs($actor)->postJson("/api/access/users/{$other->id}/roles", ['role' => $role->id])->assertOk()->assertJsonPath('data.roles.0.name', 'editor');
    expect(ActionLog::count())->toBe(2);
});

it('treats an already held role as a 200 no-op without audit', function (): void {
    $target = makeUser('t@example.test');
    $target->assignRole(makeRole('editor'));
    ActionLog::query()->delete();

    $this->actingAs(assigner())->postJson("/api/access/users/{$target->id}/roles", ['role' => 'editor'])->assertOk()->assertJsonCount(1, 'data.roles');
    expect(ActionLog::count())->toBe(0);
});

it('removes a role and audits once', function (): void {
    $target = makeUser('t@example.test');
    $role = makeRole('editor');
    $target->assignRole($role);
    ActionLog::query()->delete();

    $this->actingAs(assigner())->deleteJson("/api/access/users/{$target->id}/roles/{$role->id}")->assertOk()->assertJsonPath('data.roles', []);

    expect(ActionLog::sole()->event)->toBe('roles.removed')->and($target->fresh()->getRoleNames()->all())->toBe([]);
});

it('syncs roles, treats a reordered identical set as a no-op and accepts an empty list', function (): void {
    $target = makeUser('t@example.test');
    $a = makeRole('alpha');
    $b = makeRole('beta');
    $actor = assigner();
    ActionLog::query()->delete();

    $this->actingAs($actor)->putJson("/api/access/users/{$target->id}/roles", ['roles' => ['alpha', $b->id]])
        ->assertOk()->assertJsonCount(2, 'data.roles');
    expect(ActionLog::sole()->event)->toBe('roles.synced');

    ActionLog::query()->delete();
    $this->actingAs($actor)->putJson("/api/access/users/{$target->id}/roles", ['roles' => ['beta', 'alpha']])->assertOk();
    expect(ActionLog::count())->toBe(0);

    $this->actingAs($actor)->putJson("/api/access/users/{$target->id}/roles", ['roles' => []])->assertOk()->assertJsonPath('data.roles', []);
    expect(ActionLog::count())->toBe(1)->and($a->users()->count())->toBe(0);
});

it('answers 401 and 403 envelopes on all three endpoints', function (): void {
    $target = makeUser('t@example.test');
    $role = makeRole('editor');
    $calls = [
        ['postJson', "/api/access/users/{$target->id}/roles", ['role' => 'editor']],
        ['deleteJson', "/api/access/users/{$target->id}/roles/{$role->id}", []],
        ['putJson', "/api/access/users/{$target->id}/roles", ['roles' => []]],
    ];

    foreach ($calls as [$method, $url, $body]) {
        $this->{$method}($url, $body)->assertUnauthorized()->assertJson(['success' => false, 'code' => 'unauthenticated']);
    }

    foreach ($calls as [$method, $url, $body]) {
        $this->actingAs(makeUser(uniqid().'@example.test'))->{$method}($url, $body)->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);
    }
});

it('answers 404 for an unknown user, unknown or trashed role', function (): void {
    $actor = assigner();
    $target = makeUser('t@example.test');
    $trashed = makeRole('gone');
    $trashed->delete();

    $this->actingAs($actor)->postJson('/api/access/users/99999/roles', ['role' => 'x'])->assertNotFound()->assertJson(['code' => 'not_found']);
    $this->actingAs($actor)->putJson('/api/access/users/99999/roles', ['roles' => []])->assertNotFound();
    $this->actingAs($actor)->deleteJson("/api/access/users/99999/roles/{$trashed->id}")->assertNotFound();
    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/roles", ['role' => 'nope'])->assertNotFound()->assertJson(['code' => 'not_found']);
    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/roles", ['role' => 'gone'])->assertNotFound();
    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}/roles/{$trashed->id}")->assertNotFound();
    $this->actingAs($actor)->deleteJson("/api/access/users/{$target->id}/roles/99999")->assertNotFound();
});

it('validates the body: 422 for a missing role and for unknown or trashed roles inside sync', function (): void {
    $actor = assigner();
    $target = makeUser('t@example.test');
    makeRole('ok');
    makeRole('gone')->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/roles", [])->assertStatus(422)->assertJsonValidationErrors('role');
    $this->actingAs($actor)->putJson("/api/access/users/{$target->id}/roles", ['roles' => ['ok', 'nope']])->assertStatus(422)->assertJsonValidationErrors('roles.1');
    $this->actingAs($actor)->putJson("/api/access/users/{$target->id}/roles", ['roles' => ['gone']])->assertStatus(422)->assertJsonValidationErrors('roles.0');
    $this->actingAs($actor)->putJson("/api/access/users/{$target->id}/roles", [])->assertStatus(422)->assertJsonValidationErrors('roles');
    expect($target->fresh()->getRoleNames()->all())->toBe([]);
});

it('refuses self assignment', function (): void {
    $actor = assigner();
    makeRole('editor');
    ActionLog::query()->delete();

    $this->actingAs($actor)->postJson("/api/access/users/{$actor->id}/roles", ['role' => 'editor'])->assertForbidden()->assertJson(['code' => 'guardrail.self_role_change']);
    expect(ActionLog::count())->toBe(0);
});

it('refuses escalation when the actor lacks a permission the role has', function (): void {
    $target = makeUser('t@example.test');
    makeRole('power', ['billing.manage']);
    ActionLog::query()->delete();

    $this->actingAs(assigner())->postJson("/api/access/users/{$target->id}/roles", ['role' => 'power'])->assertForbidden()->assertJson(['code' => 'guardrail.escalation']);
    $this->actingAs(assigner())->putJson("/api/access/users/{$target->id}/roles", ['roles' => ['power']])->assertForbidden()->assertJson(['code' => 'guardrail.escalation']);
    expect(ActionLog::count())->toBe(0)->and($target->fresh()->getRoleNames()->all())->toBe([]);
});

it('lets an actor holding every permission assign the role', function (): void {
    $target = makeUser('t@example.test');
    makeRole('power', ['billing.manage']);

    $this->actingAs(assigner(['billing.manage']))->postJson("/api/access/users/{$target->id}/roles", ['role' => 'power'])->assertOk();
});

it('never lets a non-owner assign the owner role and requires confirm even from an owner', function (): void {
    $owner = makeOwner();
    $target = makeUser('t@example.test');
    ActionLog::query()->delete();

    $this->actingAs(assigner())->postJson("/api/access/users/{$target->id}/roles", ['role' => 'owner', 'confirm' => true])->assertForbidden()->assertJson(['code' => 'guardrail.escalation']);
    $this->actingAs($owner)->postJson("/api/access/users/{$target->id}/roles", ['role' => 'owner'])->assertForbidden()->assertJson(['code' => 'guardrail.high_risk_confirm_required']);
    expect(ActionLog::count())->toBe(0);

    $this->actingAs($owner)->postJson("/api/access/users/{$target->id}/roles", ['role' => 'owner', 'confirm' => true])->assertOk()->assertJsonPath('data.roles.0.name', 'owner');
    expect(ActionLog::count())->toBe(1);
});

it('refuses removing the last owner via DELETE and via PUT sync', function (): void {
    config(['roles.guardrails.min_owners' => 2]);
    $actor = makeOwner('o1@example.test');
    $second = makeOwner('o2@example.test');
    $owner = $second->roles->first();
    ActionLog::query()->delete();

    $this->actingAs($actor)->deleteJson("/api/access/users/{$second->id}/roles/{$owner->id}")->assertForbidden()->assertJson(['code' => 'guardrail.last_owner']);
    $this->actingAs($actor)->putJson("/api/access/users/{$second->id}/roles", ['roles' => []])->assertForbidden()->assertJson(['code' => 'guardrail.last_owner']);
    expect(ActionLog::count())->toBe(0)->and($second->fresh()->hasRole('owner'))->toBeTrue();
});

it('uses a constant number of queries regardless of the role permission count', function (): void {
    $measure = function (int $count): int {
        $permissions = array_map(fn (int $i): string => "gen{$count}.perm{$i}", range(1, $count));
        $actor = assigner($permissions);
        $role = makeRole("big{$count}", $permissions);
        $target = makeUser("q{$count}@example.test");

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($actor)->postJson("/api/access/users/{$target->id}/roles", ['role' => $role->name])->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $small = $measure(5);
    $large = $measure(60);

    expect($large)->toBe($small);
});

it('returns the target name and email only to actors who may view users', function (): void {
    $target = makeUser('t@example.test');
    makeRole('editor');

    $without = assigner();
    $this->actingAs($without)->postJson("/api/access/users/{$target->id}/roles", ['role' => 'editor'])->assertOk()
        ->assertJsonPath('data.user', ['id' => $target->id]);
    $this->actingAs($without)->putJson("/api/access/users/{$target->id}/roles", ['roles' => ['editor']])->assertOk()
        ->assertJsonPath('data.user', ['id' => $target->id]);

    $with = assigner(['access.users.view']);
    $this->actingAs($with)->putJson("/api/access/users/{$target->id}/roles", ['roles' => ['editor']])->assertOk()
        ->assertJsonPath('data.user', ['id' => $target->id, 'name' => 'A', 'email' => 't@example.test']);
});

it('hides name and email on removal for actors without users view', function (): void {
    $target = makeUser('t@example.test');
    $role = makeRole('editor');
    $target->assignRole($role);

    $this->actingAs(assigner())->deleteJson("/api/access/users/{$target->id}/roles/{$role->id}")->assertOk()
        ->assertJsonPath('data.user', ['id' => $target->id]);
});
