<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Guardrails\Rules\OwnerRoleLockedRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfChangeRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SystemRoleRule;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Models\Role;
use Illuminate\Database\Eloquent\Model;

/**
 * @param  list<Permission>  $added
 * @param  list<Permission>  $removed
 */
function matrixOp(?Model $actor, Role $role, array $added = [], array $removed = [], bool $confirmed = false): Operation
{
    return new Operation(T::UpdateRolePermissions, actor: $actor, role: $role, addedPermissions: $added, removedPermissions: $removed, confirmed: $confirmed);
}

beforeEach(function () {
    $this->a = makePermission('a.view');
    $this->b = makePermission('b.view');
    $this->risky = makePermission('orders.refund', 'high');
    $this->actor = makeUser('actor@example.test');
    $this->actor->givePermissionTo('a.view');
    $this->actor = $this->actor->fresh();
    $this->target = makeRole('target');
});

it('locks the owner role for every actor including the system', function () {
    $owner = makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();

    foreach ([$owner, $this->actor, null] as $actor) {
        expect(guardrailCode(matrixOp($actor, $ownerRole, [$this->a])))->toBe('guardrail.owner_role_locked')
            ->and(guardrailCode(matrixOp($actor, $ownerRole, [], [$this->a])))->toBe('guardrail.owner_role_locked');
    }
});

it('cannot switch the owner lock off through the toggles', function () {
    config(['roles.guardrails.prevent_self_escalation' => false, 'roles.guardrails.prevent_escalation' => false, 'roles.guardrails.confirm_high_risk' => false]);
    makeOwner();

    expect(guardrailCode(matrixOp(null, Role::where('name', 'owner')->first(), [$this->a])))->toBe('guardrail.owner_role_locked');
});

it('only locks the role being edited, not other roles', function () {
    makeOwner();

    expect(guardrailCode(matrixOp(null, $this->target, [$this->a])))->toBeNull();
});

it('includes the owner lock in the default rules before the self change rule', function () {
    $rules = config('roles.guardrails.rules');

    expect($rules)->toContain(OwnerRoleLockedRule::class)
        ->and(array_search(OwnerRoleLockedRule::class, $rules, true))->toBeLessThan(array_search(SelfChangeRule::class, $rules, true))
        ->and(array_search(OwnerRoleLockedRule::class, $rules, true))->toBeGreaterThan(array_search(SystemRoleRule::class, $rules, true));
});

it('blocks a non-owner from editing a role they hold, for grants and revokes', function () {
    $this->actor->assignRole($this->target);
    $actor = $this->actor->fresh();

    expect(guardrailCode(matrixOp($actor, $this->target, [$this->a])))->toBe('guardrail.self_role_change')
        ->and(guardrailCode(matrixOp($actor, $this->target, [], [$this->a])))->toBe('guardrail.self_role_change');
});

it('lets an owner and the system edit a role they hold, and anyone edit another role', function () {
    $owner = makeOwner();
    $owner->assignRole($this->target);

    expect(guardrailCode(matrixOp($owner->fresh(), $this->target, [$this->a])))->toBeNull()
        ->and(guardrailCode(matrixOp(null, $this->target, [$this->a])))->toBeNull()
        ->and(guardrailCode(matrixOp($this->actor, makeRole('other'), [$this->a])))->toBeNull();
});

it('skips the self change rule when its toggle is off', function () {
    config(['roles.guardrails.prevent_self_escalation' => false]);
    $this->actor->assignRole($this->target);

    expect(guardrailCode(matrixOp($this->actor->fresh(), $this->target, [$this->a])))->toBeNull();
});

it('blocks adding a permission the actor lacks, allows subsets and removals', function () {
    expect(guardrailCode(matrixOp($this->actor, $this->target, [$this->b])))->toBe('guardrail.escalation')
        ->and(guardrailCode(matrixOp($this->actor, $this->target, [$this->a, $this->b])))->toBe('guardrail.escalation')
        ->and(guardrailCode(matrixOp($this->actor, $this->target, [$this->a])))->toBeNull()
        ->and(guardrailCode(matrixOp($this->actor, $this->target, [], [$this->b])))->toBeNull();
});

it('skips escalation for owners, the system and with the toggle off', function () {
    $owner = makeOwner();

    expect(guardrailCode(matrixOp($owner, $this->target, [$this->b])))->toBeNull()
        ->and(guardrailCode(matrixOp(null, $this->target, [$this->b])))->toBeNull();

    config(['roles.guardrails.prevent_escalation' => false]);
    expect(guardrailCode(matrixOp($this->actor, $this->target, [$this->b])))->toBeNull();
});

it('requires confirmation for added high-risk permissions, system actor included', function () {
    $owner = makeOwner();

    foreach ([$owner, null] as $actor) {
        expect(guardrailCode(matrixOp($actor, $this->target, [$this->risky])))->toBe('guardrail.high_risk_confirm_required')
            ->and(guardrailCode(matrixOp($actor, $this->target, [$this->risky], confirmed: true)))->toBeNull();
    }
});

it('does not require confirmation for removing high-risk permissions or adding low-risk ones', function () {
    expect(guardrailCode(matrixOp(null, $this->target, [$this->a], [$this->risky])))->toBeNull();
});

it('skips the high-risk rule when its toggle is off', function () {
    config(['roles.guardrails.confirm_high_risk' => false]);

    expect(guardrailCode(matrixOp(null, $this->target, [$this->risky])))->toBeNull();
});

it('checks escalation before high-risk confirmation for a non-owner', function () {
    expect(guardrailCode(matrixOp($this->actor, $this->target, [$this->risky])))->toBe('guardrail.escalation');
});
