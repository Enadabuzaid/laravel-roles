<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestUser;

/**
 * @param  list<string>  $permissions
 */
function matrixActor(array $permissions = ['access.permissions.view', 'access.roles.update']): TestUser
{
    $user = makeUser(uniqid().'@example.test');
    $user->assignRole(makeRole('matrix-actor-'.uniqid(), $permissions));

    return $user->fresh();
}

beforeEach(function () {
    $this->a = makePermission('a.view');
    $this->b = makePermission('b.view');
    $this->risky = makePermission('orders.refund', 'high');
    $this->one = makeRole('one', ['a.view']);
    $this->two = makeRole('two');
});

it('requires authentication and permissions', function () {
    $this->getJson('/api/access/matrix')->assertUnauthorized();
    $this->putJson('/api/access/matrix', ['changes' => []])->assertUnauthorized();
    $this->putJson("/api/access/roles/{$this->one->id}/permissions", [])->assertUnauthorized();

    $nobody = makeUser('nobody@example.test');
    $this->actingAs($nobody)->getJson('/api/access/matrix')->assertForbidden();
    $this->actingAs($nobody)->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['grant' => [$this->a->id]]]])->assertForbidden();
    $this->actingAs($nobody)->putJson("/api/access/roles/{$this->one->id}/permissions", ['grant' => ['a.view']])->assertForbidden();

    $viewer = matrixActor(['access.permissions.view']);
    $this->actingAs($viewer)->getJson('/api/access/matrix')->assertOk();
    $this->actingAs($viewer)->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['grant' => [$this->a->id]]]])->assertForbidden();
});

it('returns modules, roles with compact permission ids and a summary', function () {
    $actor = matrixActor();

    $response = $this->actingAs($actor)->getJson('/api/access/matrix')->assertOk();

    $response->assertJsonStructure(['data' => [
        'modules' => [['key', 'label', 'permissions' => [['id', 'name', 'label_text', 'label', 'risk', 'deprecated']]]],
        'roles' => [['id', 'name', 'label_text', 'color', 'is_system', 'is_owner', 'locked', 'permission_ids']],
        'summary' => ['roles', 'permissions', 'grants'],
    ]]);

    $one = collect($response->json('data.roles'))->firstWhere('name', 'one');
    expect($one['permission_ids'])->toBe([$this->a->id])->and($one['locked'])->toBeFalse()
        ->and($response->json('data.summary.roles'))->toBe(Role::count());
});

it('flags the owner role, held roles and non-editable viewers as locked', function () {
    makeOwner();
    $actor = matrixActor();
    $actor->assignRole($this->one);
    $actor = $actor->fresh();

    $locked = fn ($user) => collect($this->actingAs($user)->getJson('/api/access/matrix')->json('data.roles'))->pluck('locked', 'name');

    $byName = $locked($actor);
    expect($byName['owner'])->toBeTrue()->and($byName['one'])->toBeTrue()->and($byName['two'])->toBeFalse();

    $viewer = matrixActor(['access.permissions.view']);
    expect($locked($viewer)->every(fn (bool $flag) => $flag))->toBeTrue();
});

it('lets an owner hold and edit other roles but never the owner column', function () {
    $owner = makeOwner();
    $owner->assignRole($this->one);

    $byName = collect($this->actingAs($owner->fresh())->getJson('/api/access/matrix')->json('data.roles'))->pluck('locked', 'name');

    expect($byName['owner'])->toBeTrue()->and($byName['one'])->toBeFalse();
});

it('applies a matrix update and reports roles and summary', function () {
    $actor = matrixActor();
    $actor->givePermissionTo('a.view', 'b.view');
    ActionLog::query()->delete();

    $this->actingAs($actor->fresh())->putJson('/api/access/matrix', ['changes' => [
        $this->one->id => ['grant' => [$this->b->id], 'revoke' => [$this->a->id]],
        $this->two->id => ['grant' => ['a.view']],
    ]])->assertOk()
        ->assertJsonPath("data.roles.{$this->one->id}", ['added' => ['b.view'], 'removed' => ['a.view']])
        ->assertJsonPath('data.summary', ['granted' => 2, 'revoked' => 1, 'roles_changed' => 2, 'high_risk_granted' => 0]);

    expect($this->one->fresh()->permissions->pluck('name')->all())->toBe(['b.view'])->and(ActionLog::count())->toBe(2);
});

it('answers every guardrail over HTTP with its stable code', function () {
    makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();
    $actor = matrixActor();
    $actor->givePermissionTo('a.view');
    $held = makeRole('held');
    $actor->assignRole($held);
    $actor = $actor->fresh();

    $matrix = fn (array $changes) => $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => $changes]);

    $matrix([$ownerRole->id => ['grant' => ['a.view']]])->assertForbidden()->assertJsonPath('code', 'guardrail.owner_role_locked');
    $matrix([$held->id => ['grant' => ['a.view']]])->assertForbidden()->assertJsonPath('code', 'guardrail.self_role_change');
    $matrix([$this->two->id => ['grant' => ['b.view']]])->assertForbidden()->assertJsonPath('code', 'guardrail.escalation');

    $owner = makeOwner('o2@example.test');
    $this->actingAs($owner)->putJson('/api/access/matrix', ['changes' => [$this->two->id => ['grant' => ['orders.refund']]]])
        ->assertForbidden()->assertJsonPath('code', 'guardrail.high_risk_confirm_required');
    $this->actingAs($owner)->putJson('/api/access/matrix', ['confirm' => true, 'changes' => [$this->two->id => ['grant' => ['orders.refund']]]])
        ->assertOk()->assertJsonPath('data.summary.high_risk_granted', 1);

    expect($this->two->fresh()->permissions->pluck('name')->all())->toBe(['orders.refund']);
});

it('does not let a non-owner who holds a role widen it through either endpoint', function () {
    $actor = matrixActor();
    $actor->givePermissionTo('a.view', 'b.view');
    $actor->assignRole($this->one);
    $actor = $actor->fresh();

    $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['grant' => [$this->b->id]]]])
        ->assertForbidden()->assertJsonPath('code', 'guardrail.self_role_change');
    $this->actingAs($actor)->putJson("/api/access/roles/{$this->one->id}/permissions", ['grant' => ['b.view']])
        ->assertForbidden()->assertJsonPath('code', 'guardrail.self_role_change');

    expect($this->one->fresh()->permissions->pluck('name')->all())->toBe(['a.view']);
});

it('keeps the owner column uneditable for everyone through both endpoints', function () {
    $owner = makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();
    ActionLog::query()->delete();

    $this->actingAs($owner)->putJson('/api/access/matrix', ['confirm' => true, 'changes' => [$ownerRole->id => ['grant' => ['a.view']]]])
        ->assertForbidden()->assertJsonPath('code', 'guardrail.owner_role_locked');
    $this->actingAs($owner)->putJson("/api/access/roles/{$ownerRole->id}/permissions", ['confirm' => true, 'revoke' => ['a.view']])
        ->assertForbidden()->assertJsonPath('code', 'guardrail.owner_role_locked');

    expect($ownerRole->fresh()->permissions)->toHaveCount(0)->and(ActionLog::count())->toBe(0);
});

it('rejects malformed bodies and unknown ids', function () {
    $actor = matrixActor();
    $put = fn (array $body) => $this->actingAs($actor)->putJson('/api/access/matrix', $body);

    $put([])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    $put(['changes' => []])->assertStatus(422);
    $put(['changes' => 'x'])->assertStatus(422);
    $put(['changes' => ['abc' => ['grant' => [1]]]])->assertStatus(422);
    $put(['changes' => [0 => ['grant' => [1]]]])->assertStatus(422);
    $put(['changes' => [$this->one->id => ['grant' => 'a.view']]])->assertStatus(422);
    $put(['changes' => [$this->one->id => ['grant' => [['x']]]]])->assertStatus(422);
    $put(['changes' => [$this->one->id => ['grant' => [0]]]])->assertStatus(422);
    $put(['changes' => [$this->one->id => ['grant' => [true]]]])->assertStatus(422);
    $put(['changes' => [$this->one->id => ['grant' => ['a.view']]], 'confirm' => 'maybe'])->assertStatus(422);

    $put(['changes' => [999999 => ['grant' => ['a.view']]]])->assertNotFound()->assertJsonPath('code', 'not_found');
    $put(['changes' => [$this->one->id => ['grant' => ['ghost.view']]]])->assertNotFound()->assertJsonPath('code', 'permission.not_found');
    $put(['changes' => [$this->one->id => ['grant' => [999999]]]])->assertNotFound()->assertJsonPath('code', 'permission.not_found');
});

it('enforces payload limits', function () {
    $actor = matrixActor();
    $roles = [];
    for ($i = 1; $i <= 201; $i++) {
        $roles[$i] = ['grant' => [1]];
    }

    $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => $roles])->assertStatus(422)->assertJsonPath('code', 'validation_failed');

    $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['grant' => range(1, 501)]]])->assertStatus(422);
    $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['revoke' => range(1, 501)]]])->assertStatus(422);
    $this->actingAs($actor)->putJson("/api/access/roles/{$this->one->id}/permissions", ['grant' => range(1, 501)])->assertStatus(422);
});

it('refuses deprecated grants with a 409', function () {
    $this->b->forceFill(['deprecated_at' => now()])->save();

    $this->actingAs(matrixActor())->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['grant' => [$this->b->id]]]])
        ->assertStatus(409)->assertJsonPath('code', 'permission.deprecated');
});

it('updates one role through the role permissions endpoint', function () {
    $actor = matrixActor();
    $actor->givePermissionTo('a.view', 'b.view');
    ActionLog::query()->delete();

    $this->actingAs($actor->fresh())->putJson("/api/access/roles/{$this->one->id}/permissions", ['grant' => ['b.view'], 'revoke' => [$this->a->id]])
        ->assertOk()
        ->assertJsonPath('data.added', ['b.view'])
        ->assertJsonPath('data.removed', ['a.view'])
        ->assertJsonPath('data.role.name', 'one')
        ->assertJsonPath('data.role.permissions.0.name', 'b.view');

    expect(ActionLog::sole()->event)->toBe('roles.permissions_updated');
});

it('mirrors the guardrails and bounds on the role permissions endpoint', function () {
    $actor = matrixActor();
    $url = "/api/access/roles/{$this->two->id}/permissions";

    $this->actingAs($actor)->putJson($url, ['grant' => ['a.view']])->assertForbidden()->assertJsonPath('code', 'guardrail.escalation');
    $this->actingAs(makeOwner())->putJson($url, ['grant' => ['orders.refund']])->assertForbidden()->assertJsonPath('code', 'guardrail.high_risk_confirm_required');
    $this->actingAs(makeOwner('o3@example.test'))->putJson($url, ['grant' => ['orders.refund'], 'confirm' => true])->assertOk();
    $this->actingAs($actor)->putJson($url, ['grant' => 'a.view'])->assertStatus(422);
    $this->actingAs($actor)->putJson($url, ['grant' => ['ghost.view']])->assertNotFound()->assertJsonPath('code', 'permission.not_found');
    $this->actingAs($actor)->putJson('/api/access/roles/abc/permissions', [])->assertNotFound();
});

it('answers 404 for trashed and unknown roles on the role permissions endpoint', function () {
    $actor = matrixActor();
    $this->two->delete();

    $this->actingAs($actor)->putJson("/api/access/roles/{$this->two->id}/permissions", ['grant' => ['a.view']])->assertNotFound();
    $this->actingAs($actor)->putJson('/api/access/roles/999999/permissions', ['grant' => ['a.view']])->assertNotFound();
});

it('applies a change immediately to the permission cache', function () {
    $actor = matrixActor();
    $actor->givePermissionTo('a.view');
    $member = makeUser('member@example.test');
    $member->assignRole($this->two);
    expect($member->fresh()->checkPermissionTo('a.view'))->toBeFalse();

    $this->actingAs($actor->fresh())->putJson('/api/access/matrix', ['changes' => [$this->two->id => ['grant' => ['a.view']]]])->assertOk();

    expect($member->fresh()->checkPermissionTo('a.view'))->toBeTrue();
});

it('omits trashed roles and keeps held deprecated permissions in the matrix', function () {
    $this->two->delete();
    $this->a->forceFill(['deprecated_at' => now()])->save();
    $this->b->forceFill(['deprecated_at' => now()])->save();

    $data = $this->actingAs(matrixActor())->getJson('/api/access/matrix')->json('data');
    $names = collect($data['modules'])->flatMap(fn ($module) => collect($module['permissions'])->pluck('name'))->all();

    expect(collect($data['roles'])->pluck('name')->all())->not->toContain('two')
        ->and($names)->toContain('a.view')->not->toContain('b.view')
        ->and(collect($data['modules'])->flatMap(fn ($module) => $module['permissions'])->firstWhere('name', 'a.view')['deprecated'])->toBeTrue();
});

function pivotSnapshot(): array
{
    return DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->map(fn ($row) => (array) $row)->all();
}

it('refuses every guardrail on both endpoints without changing anything', function (string $endpoint, string $guardrail) {
    makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();
    $target = $this->two;
    $actor = matrixActor();
    $actor->givePermissionTo('a.view');
    $payload = ['grant' => ['a.view']];

    switch ($guardrail) {
        case 'owner_role_locked':
            $actor = makeOwner('boss@example.test');
            $target = $ownerRole;
            break;
        case 'self_role_change':
            $actor->assignRole($target);
            break;
        case 'escalation':
            $payload = ['grant' => ['b.view']];
            break;
        case 'high_risk_confirm_required':
            $actor = makeOwner('boss@example.test');
            $payload = ['grant' => ['orders.refund']];
            break;
    }

    $actor = $actor->fresh();
    $pivot = pivotSnapshot();
    ActionLog::query()->delete();

    $response = $endpoint === 'matrix'
        ? $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => [$target->id => $payload]])
        : $this->actingAs($actor)->putJson("/api/access/roles/{$target->id}/permissions", $payload);

    $response->assertForbidden()->assertJsonPath('code', "guardrail.{$guardrail}");
    expect(pivotSnapshot())->toBe($pivot)->and(ActionLog::count())->toBe(0);
})->with(['matrix', 'role'])->with(['owner_role_locked', 'self_role_change', 'escalation', 'high_risk_confirm_required']);

it('is atomic over HTTP when the violation is on the second processed role', function (string $guardrail) {
    expect($this->one->id)->toBeLessThan($this->two->id);

    makePermission('c.view');
    $actor = matrixActor();
    $actor->givePermissionTo('a.view', 'b.view');

    if ($guardrail === 'self_role_change') {
        $actor->assignRole($this->two);
    }

    $actor = $actor->fresh();
    $pivot = pivotSnapshot();
    ActionLog::query()->delete();

    // `one` gets a real change (b.view, which the actor holds); the violation is on `two`, processed second.
    $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => [
        $this->two->id => ['grant' => [$guardrail === 'escalation' ? 'c.view' : 'b.view']],
        $this->one->id => ['grant' => ['b.view']],
    ]])->assertForbidden()->assertJsonPath('code', "guardrail.{$guardrail}");

    expect(pivotSnapshot())->toBe($pivot)->and(ActionLog::count())->toBe(0)
        ->and($this->one->fresh()->permissions->pluck('name')->all())->toBe(['a.view'])
        ->and($this->two->fresh()->permissions)->toHaveCount(0);
})->with(['self_role_change', 'escalation']);

it('treats a role of another guard as 404 on both endpoints', function () {
    $foreign = Role::create(['name' => 'api-role', 'guard_name' => 'api']);
    $actor = matrixActor();

    $this->actingAs($actor)->putJson("/api/access/roles/{$foreign->id}/permissions", ['grant' => ['a.view']])->assertNotFound();
    $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => [$foreign->id => ['grant' => ['a.view']]]])->assertNotFound();
});

it('caps the total references of a matrix payload', function () {
    $actor = matrixActor();
    $changes = [];
    for ($i = 1; $i <= 11; $i++) {
        $changes[$i] = ['grant' => range(1, 400), 'revoke' => range(401, 500)];
    }

    $this->actingAs($actor)->putJson('/api/access/matrix', ['changes' => $changes])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
});

it('rejects conflicting grant and revoke with 422', function () {
    $this->actingAs(matrixActor())->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['grant' => ['b.view'], 'revoke' => [$this->b->id]]]])
        ->assertStatus(422)->assertJsonPath('code', 'permission.conflicting_changes');
    $this->actingAs(matrixActor())->putJson("/api/access/roles/{$this->one->id}/permissions", ['grant' => ['b.view'], 'revoke' => ['b.view']])
        ->assertStatus(422)->assertJsonPath('code', 'permission.conflicting_changes');
});

it('treats granting an already held deprecated permission as a 200 no-op', function () {
    $this->a->forceFill(['deprecated_at' => now()])->save();
    ActionLog::query()->delete();

    $this->actingAs(matrixActor())->putJson('/api/access/matrix', ['changes' => [$this->one->id => ['grant' => [$this->a->id]]]])
        ->assertOk()->assertJsonPath('data.summary.roles_changed', 0);

    expect(ActionLog::count())->toBe(0);
});

it('detects the owner through the configured guard when computing locks', function () {
    $actor = matrixActor();
    $actor->assignRole($this->one);
    $foreignOwner = Role::create(['name' => 'owner', 'guard_name' => 'api']);
    DB::table('model_has_roles')->insert(['role_id' => $foreignOwner->id, 'model_type' => $actor->getMorphClass(), 'model_id' => $actor->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $byName = collect($this->actingAs($actor->fresh())->getJson('/api/access/matrix')->json('data.roles'))->pluck('locked', 'name');

    expect($byName['one'])->toBeTrue();
});
