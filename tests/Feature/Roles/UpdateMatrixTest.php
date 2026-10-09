<?php

declare(strict_types=1);

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Actions\UpdateMatrix;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

function updateMatrix(array $changes, bool $confirmed = false, ?Model $actor = null): array
{
    return app(ActorContext::class)->runAs($actor, fn () => app(UpdateMatrix::class)->handle($changes, $confirmed));
}

beforeEach(function () {
    $this->a = makePermission('a.view');
    $this->b = makePermission('b.view');
    $this->risky = makePermission('orders.refund', 'high');
    $this->one = makeRole('one', ['a.view']);
    $this->two = makeRole('two', ['b.view']);
    ActionLog::query()->delete();
});

it('applies a multi-role change and reports exact numbers', function () {
    $result = updateMatrix([
        $this->one->id => ['grant' => [$this->b->id], 'revoke' => [$this->a->id]],
        $this->two->id => ['grant' => [$this->a->id, $this->risky->id], 'revoke' => []],
    ], confirmed: true);

    expect($result['roles'][$this->one->id])->toBe(['added' => ['b.view'], 'removed' => ['a.view']])
        ->and($result['roles'][$this->two->id]['added'])->toEqualCanonicalizing(['a.view', 'orders.refund'])
        ->and($result['summary'])->toBe(['granted' => 3, 'revoked' => 1, 'roles_changed' => 2, 'high_risk_granted' => 1])
        ->and($this->one->fresh()->permissions->pluck('name')->all())->toBe(['b.view'])
        ->and($this->two->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['a.view', 'b.view', 'orders.refund'])
        ->and(ActionLog::count())->toBe(2);
});

it('counts untouched roles and no-ops as unchanged', function () {
    $result = updateMatrix([
        $this->one->id => ['grant' => ['a.view'], 'revoke' => []],
        $this->two->id => ['grant' => ['a.view'], 'revoke' => []],
    ]);

    expect($result['summary'])->toBe(['granted' => 1, 'revoked' => 0, 'roles_changed' => 1, 'high_risk_granted' => 0])
        ->and(ActionLog::count())->toBe(1);
});

it('leaves the first role unchanged and writes no audit when the second role violates a guardrail', function () {
    expect(fn () => updateMatrix([
        $this->one->id => ['grant' => [$this->b->id], 'revoke' => []],
        $this->two->id => ['grant' => [$this->risky->id], 'revoke' => []],
    ]))->toThrow(function (AccessException $e) {
        expect($e->errorCode)->toBe('guardrail.high_risk_confirm_required');
    });

    expect($this->one->fresh()->permissions->pluck('name')->all())->toBe(['a.view'])
        ->and($this->two->fresh()->permissions->pluck('name')->all())->toBe(['b.view'])
        ->and(ActionLog::count())->toBe(0);
});

it('leaves everything unchanged when a later role does not exist or a permission is unknown', function () {
    expect(fn () => updateMatrix([
        $this->one->id => ['grant' => [$this->b->id], 'revoke' => []],
        999999 => ['grant' => [$this->a->id], 'revoke' => []],
    ]))->toThrow(ModelNotFoundException::class);

    expect(fn () => updateMatrix([
        $this->one->id => ['grant' => [$this->b->id], 'revoke' => []],
        $this->two->id => ['grant' => ['ghost.view'], 'revoke' => []],
    ]))->toThrow(AccessException::class);

    expect($this->one->fresh()->permissions->pluck('name')->all())->toBe(['a.view'])->and(ActionLog::count())->toBe(0);
});

it('threads the confirm flag through', function () {
    expect(updateMatrix([$this->one->id => ['grant' => [$this->risky->id], 'revoke' => []]], confirmed: true)['summary']['high_risk_granted'])->toBe(1);
});

it('never edits the owner column, and a violation there blocks the whole request', function () {
    makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();
    ActionLog::query()->delete();

    expect(fn () => updateMatrix([
        $this->one->id => ['grant' => [$this->b->id], 'revoke' => []],
        $ownerRole->id => ['grant' => [$this->a->id], 'revoke' => []],
    ]))->toThrow(function (AccessException $e) {
        expect($e->errorCode)->toBe('guardrail.owner_role_locked');
    });

    expect($ownerRole->fresh()->permissions)->toHaveCount(0)->and($this->one->fresh()->permissions->pluck('name')->all())->toBe(['a.view']);
});

it('evaluates every guardrail before any mutation', function () {
    $mutations = 0;
    DB::listen(function ($query) use (&$mutations) {
        if (preg_match('/^(insert|delete)\b.*role_has_permissions/i', $query->sql)) {
            $mutations++;
        }
    });

    try {
        updateMatrix([
            $this->one->id => ['grant' => [$this->b->id], 'revoke' => []],
            $this->two->id => ['grant' => [$this->risky->id], 'revoke' => []],
        ]);
    } catch (AccessException) {
    }

    expect($mutations)->toBe(0);
});

it('blocks a non-owner holding a role from widening it as part of a matrix change', function () {
    $actor = makeUser('actor@example.test');
    $actor->givePermissionTo('a.view', 'b.view');
    $actor->assignRole($this->two);
    $actor = $actor->fresh();
    ActionLog::query()->delete();

    // Roles are processed in id order, so the held role (two, the higher id) is the second one.
    expect($this->one->id)->toBeLessThan($this->two->id);

    expect(fn () => updateMatrix([
        $this->one->id => ['grant' => [$this->b->id], 'revoke' => []],
        $this->two->id => ['grant' => [$this->a->id], 'revoke' => []],
    ], actor: $actor))->toThrow(function (AccessException $e) {
        expect($e->errorCode)->toBe('guardrail.self_role_change');
    });

    expect($this->one->fresh()->permissions->pluck('name')->all())->toBe(['a.view'])
        ->and($this->two->fresh()->permissions->pluck('name')->all())->toBe(['b.view'])
        ->and(ActionLog::count())->toBe(0);
});

it('ignores roles of another guard as unknown', function () {
    $foreign = Role::create(['name' => 'api-role', 'guard_name' => 'api']);

    expect(fn () => updateMatrix([$foreign->id => ['grant' => [$this->a->id]]]))->toThrow(ModelNotFoundException::class);
});
