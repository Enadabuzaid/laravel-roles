<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Models\Role;

beforeEach(function () {
    $this->actor = makeUser('actor@example.test');
    makePermission('a.view');
    makePermission('b.view');
    makePermission('danger.run', 'high');
});

it('blocks restoring a role the actor holds, including a trashed one', function () {
    $role = makeRole('held', ['a.view']);
    $this->actor->assignRole($role);
    $role->delete();

    expect(guardrailCode(new Operation(T::RestoreRole, actor: $this->actor, role: $role)))->toBe('guardrail.self_role_change');
});

it('exempts owners and the system actor from the self restore rule', function () {
    $owner = makeOwner();
    $role = makeRole('held', ['a.view']);
    $owner->assignRole($role);
    $role->delete();

    expect(guardrailCode(new Operation(T::RestoreRole, actor: $owner, role: $role)))->toBeNull()
        ->and(guardrailCode(new Operation(T::RestoreRole, actor: null, role: $role)))->toBeNull();
});

it('lets the self rule be switched off by its toggle', function () {
    $role = makeRole('held', ['a.view']);
    $this->actor->assignRole($role);
    $this->actor->givePermissionTo('a.view');
    $role->delete();
    config(['roles.guardrails.prevent_self_escalation' => false]);

    expect(guardrailCode(new Operation(T::RestoreRole, actor: $this->actor, role: $role)))->toBeNull();
});

it('blocks restoring a role holding permissions the actor lacks', function () {
    $this->actor->givePermissionTo('a.view');
    $role = makeRole('wide', ['a.view', 'b.view']);
    $role->delete();

    expect(guardrailCode(new Operation(T::RestoreRole, actor: $this->actor, role: $role)))->toBe('guardrail.escalation');
});

it('allows restoring a subset of the actor permissions', function () {
    $this->actor->givePermissionTo('a.view');
    $role = makeRole('narrow', ['a.view']);
    $role->delete();

    expect(guardrailCode(new Operation(T::RestoreRole, actor: $this->actor, role: $role)))->toBeNull();
});

it('refuses a non-owner actor restoring the owner role', function () {
    makeOwner();
    $ownerRole = Role::where('name', 'owner')->first();
    $ownerRole->delete();

    expect(guardrailCode(new Operation(T::RestoreRole, actor: $this->actor, role: $ownerRole)))->toBe('guardrail.escalation');
    expect(guardrailCode(new Operation(T::RestoreRole, actor: null, role: $ownerRole, confirmed: true)))->toBeNull();
});

it('requires confirmation to restore a role with high-risk permissions', function () {
    $this->actor->givePermissionTo(['a.view', 'danger.run']);
    $role = makeRole('risky', ['danger.run']);
    $role->delete();

    expect(guardrailCode(new Operation(T::RestoreRole, actor: $this->actor, role: $role)))->toBe('guardrail.high_risk_confirm_required')
        ->and(guardrailCode(new Operation(T::RestoreRole, actor: $this->actor, role: $role, confirmed: true)))->toBeNull();
});

it('requires confirmation to restore the owner role', function () {
    makeOwner();
    $role = Role::where('name', 'owner')->first();
    $role->delete();

    expect(guardrailCode(new Operation(T::RestoreRole, actor: null, role: $role)))->toBe('guardrail.high_risk_confirm_required')
        ->and(guardrailCode(new Operation(T::RestoreRole, actor: null, role: $role, confirmed: true)))->toBeNull();
});
