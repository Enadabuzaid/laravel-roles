<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;
use Enadstack\LaravelRoles\Models\Role;

beforeEach(function () {
    $this->actor = makeUser('actor@example.test');
    makePermission('a.view');
    makePermission('b.view');
    $this->actor->givePermissionTo('a.view');
    $this->target = makeUser('target@example.test');
});

it('blocks granting permissions the actor lacks', function () {
    $role = makeRole('wide', ['a.view', 'b.view']);

    expect(guardrailCode(new Operation(T::AssignRole, actor: $this->actor, target: $this->target, added: [$role])))->toBe('guardrail.escalation');
});

it('allows granting a subset of the actor permissions', function () {
    $role = makeRole('narrow', ['a.view']);

    expect(guardrailCode(new Operation(T::AssignRole, actor: $this->actor, target: $this->target, added: [$role])))->toBeNull();
});

it('skips owner actors and the system actor', function () {
    $role = makeRole('wide', ['a.view', 'b.view']);
    $owner = makeOwner();

    expect(guardrailCode(new Operation(T::AssignRole, actor: $owner, target: $this->target, added: [$role])))->toBeNull()
        ->and(guardrailCode(new Operation(T::AssignRole, actor: null, target: $this->target, added: [$role])))->toBeNull();
});

it('blocks adding or removing the owner role as a non-owner', function () {
    makeOwner();
    $owner = Role::where('name', 'owner')->first();

    expect(guardrailCode(new Operation(T::AssignRole, actor: $this->actor, target: $this->target, added: [$owner])))->toBe('guardrail.escalation')
        ->and(guardrailCode(new Operation(T::RemoveRole, actor: $this->actor, target: $this->target, removed: [$owner])))->toBe('guardrail.escalation');
});

it('is skipped when prevent_escalation is off', function () {
    config(['roles.guardrails.prevent_escalation' => false]);
    $role = makeRole('wide', ['a.view', 'b.view']);

    expect(guardrailCode(new Operation(T::AssignRole, actor: $this->actor, target: $this->target, added: [$role])))->toBeNull();
});

it('lets a non-owner actor remove a non-owner role', function () {
    $role = makeRole('narrow', ['a.view']);

    expect(guardrailCode(new Operation(T::RemoveRole, actor: $this->actor, target: $this->target, removed: [$role])))->toBeNull();
});
