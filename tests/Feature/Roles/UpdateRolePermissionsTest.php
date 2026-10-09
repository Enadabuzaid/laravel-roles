<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\UpdateRolePermissions;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

function updatePermissions(Role $role, array $grant = [], array $revoke = [], bool $confirmed = false, ?Model $actor = null): array
{
    return app(ActorContext::class)->runAs($actor, fn () => app(UpdateRolePermissions::class)->handle($role, $grant, $revoke, $confirmed));
}

function expectAccessError(Closure $callback, string $code, int $status): void
{
    try {
        $callback();
        test()->fail("Expected {$code}");
    } catch (AccessException $e) {
        expect($e->errorCode)->toBe($code)->and($e->status)->toBe($status);
    }
}

beforeEach(function () {
    $this->a = makePermission('a.view');
    $this->b = makePermission('b.view');
    $this->c = makePermission('c.view');
    $this->role = makeRole('editor', ['a.view']);
    ActionLog::query()->delete();
});

it('grants and revokes by id or name and reports the names', function () {
    $result = updatePermissions($this->role, [$this->b->id, 'c.view'], [$this->a->id]);

    expect($result['added'])->toEqualCanonicalizing(['b.view', 'c.view'])->and($result['removed'])->toBe(['a.view'])
        ->and($this->role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['b.view', 'c.view']);
});

it('writes nothing, audits nothing and keeps the cache for a no-op', function () {
    DB::enableQueryLog();
    $result = updatePermissions($this->role, ['a.view'], ['b.view']);
    $writes = collect(DB::getQueryLog())->filter(fn (array $q): bool => preg_match('/^(insert|update|delete)/i', $q['query']) === 1)->count();

    expect($result)->toBe(['added' => [], 'removed' => []])->and($writes)->toBe(0)->and(ActionLog::count())->toBe(0);
});

it('rejects an unknown permission with 404 and changes nothing', function () {
    expectAccessError(fn () => updatePermissions($this->role, [$this->b->id, 999999]), 'permission.not_found', 404);
    expectAccessError(fn () => updatePermissions($this->role, ['nope.view']), 'permission.not_found', 404);
    expectAccessError(fn () => updatePermissions($this->role, [], ['nope.view']), 'permission.not_found', 404);

    expect($this->role->fresh()->permissions->pluck('name')->all())->toBe(['a.view']);
});

it('ignores permissions of another guard as unknown', function () {
    $other = Permission::create(['name' => 'api.thing', 'guard_name' => 'api']);

    expectAccessError(fn () => updatePermissions($this->role, [$other->id]), 'permission.not_found', 404);
});

it('refuses to grant a deprecated permission but may revoke it', function () {
    $this->b->forceFill(['deprecated_at' => now()])->save();
    $this->a->forceFill(['deprecated_at' => now()])->save();

    expectAccessError(fn () => updatePermissions($this->role, [$this->b->id]), 'permission.deprecated', 409);
    expect($this->role->fresh()->permissions->pluck('name')->all())->toBe(['a.view']);

    expect(updatePermissions($this->role, [], [$this->a->id])['removed'])->toBe(['a.view']);
});

it('rejects a permission listed in both grant and revoke', function () {
    expectAccessError(fn () => updatePermissions($this->role, ['b.view'], ['b.view']), 'permission.conflicting_changes', 422);
});

it('records one audit entry with names and info severity', function () {
    updatePermissions($this->role, ['b.view'], ['a.view']);

    $log = ActionLog::sole();
    expect($log->event)->toBe('roles.permissions_updated')->and($log->module)->toBe('roles')->and($log->severity)->toBe('info')
        ->and($log->subject_id)->toBe($this->role->id)
        ->and($log->old_values)->toEqual(['permissions' => ['a.view']])->and($log->new_values)->toEqual(['permissions' => ['b.view']])
        ->and($log->meta)->toEqual(['added' => ['b.view'], 'removed' => ['a.view']]);
});

it('records a warning when a high-risk permission is added', function () {
    makePermission('orders.refund', 'high');
    updatePermissions($this->role, ['orders.refund'], confirmed: true);

    expect(ActionLog::sole()->severity)->toBe('warning');
});

it('requires confirmation for high-risk grants even for the system actor, changing nothing', function () {
    makePermission('orders.refund', 'high');

    expectAccessError(fn () => updatePermissions($this->role, ['orders.refund']), 'guardrail.high_risk_confirm_required', 403);
    expect($this->role->fresh()->permissions->pluck('name')->all())->toBe(['a.view'])->and(ActionLog::count())->toBe(0);
});

it('refuses to edit the owner role for anyone and leaves it untouched', function () {
    $owner = makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();
    ActionLog::query()->delete();

    foreach ([null, $owner] as $actor) {
        expectAccessError(fn () => updatePermissions($ownerRole, ['a.view'], actor: $actor), 'guardrail.owner_role_locked', 403);
        expectAccessError(fn () => updatePermissions($ownerRole, [], ['a.view'], actor: $actor), 'guardrail.owner_role_locked', 403);
    }

    expect($ownerRole->fresh()->permissions)->toHaveCount(0)->and(ActionLog::count())->toBe(0);
});

it('blocks a non-owner holding the role and an escalating grant', function () {
    $actor = makeUser('actor@example.test');
    $actor->givePermissionTo('a.view');
    $actor->assignRole($this->role);
    $actor = $actor->fresh();

    expectAccessError(fn () => updatePermissions($this->role, [], ['a.view'], actor: $actor), 'guardrail.self_role_change', 403);

    $other = makeRole('other');
    expectAccessError(fn () => updatePermissions($other, ['b.view'], actor: $actor), 'guardrail.escalation', 403);
    expect($other->fresh()->permissions)->toHaveCount(0)->and(ActionLog::count())->toBe(0);
    expect(updatePermissions($other, ['a.view'], actor: $actor)['added'])->toBe(['a.view']);
});

it('fails closed outside console without an actor', function () {
    $context = Mockery::mock(ActorContext::class)->makePartial();
    $context->shouldReceive('actor')->andReturn(null);
    $context->shouldReceive('runningInConsole')->andReturn(false);
    app()->instance(ActorContext::class, $context);

    expectAccessError(fn () => app(UpdateRolePermissions::class)->handle($this->role, ['b.view'], []), 'guardrail.actor_required', 403);
    expect($this->role->fresh()->permissions->pluck('name')->all())->toBe(['a.view']);
});

it('clears the permission cache so a check reflects the change immediately', function () {
    $user = makeUser('u@example.test');
    $user->assignRole($this->role);

    expect($user->fresh()->checkPermissionTo('b.view'))->toBeFalse();

    updatePermissions($this->role, ['b.view'], ['a.view']);

    $fresh = $user->fresh();
    expect($fresh->checkPermissionTo('b.view'))->toBeTrue()->and($fresh->checkPermissionTo('a.view'))->toBeFalse();
});

it('does not touch a trashed role', function () {
    $this->role->delete();

    expect(fn () => updatePermissions($this->role, ['b.view']))->toThrow(ModelNotFoundException::class);
});

it('counts permissions an actor holds only through a role when checking escalation', function () {
    $actor = makeUser('via-role@example.test');
    $actor->assignRole(makeRole('holder', ['b.view']));
    $actor = $actor->fresh();
    expect($actor->getDirectPermissions())->toHaveCount(0);

    $other = makeRole('other');

    expect(updatePermissions($other, ['b.view'], actor: $actor)['added'])->toBe(['b.view']);
    expectAccessError(fn () => updatePermissions($other, ['c.view'], actor: $actor), 'guardrail.escalation', 403);
});

it('treats a role of another guard as not found', function () {
    $foreign = Role::create(['name' => 'api-role', 'guard_name' => 'api']);

    expect(fn () => updatePermissions($foreign, ['a.view']))->toThrow(ModelNotFoundException::class);
});
