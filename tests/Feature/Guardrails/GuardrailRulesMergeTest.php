<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\Rules\DestructiveConfirmationRule;
use Enadstack\LaravelRoles\Guardrails\Rules\EscalationRule;
use Enadstack\LaravelRoles\Guardrails\Rules\HighRiskConfirmRule;
use Enadstack\LaravelRoles\Guardrails\Rules\LastOwnerRule;
use Enadstack\LaravelRoles\Guardrails\Rules\OwnerRoleLockedRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfChangeRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfDestructiveRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SystemRoleRule;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\RolesServiceProvider;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Tests\Support\HostExtraRule;

function mergedRules(array $hostGuardrails): array
{
    config(['roles.guardrails' => $hostGuardrails]);
    (new RolesServiceProvider(app()))->register();
    (new RolesServiceProvider(app()))->register();

    return config('roles.guardrails.rules');
}

function builtInRules(): array
{
    return [SystemRoleRule::class, OwnerRoleLockedRule::class, SelfChangeRule::class, SelfDestructiveRule::class, EscalationRule::class,
        LastOwnerRule::class, HighRiskConfirmRule::class, DestructiveConfirmationRule::class];
}

it('gives all built-in rules to a host config without rules', function () {
    expect(mergedRules(['min_owners' => 1]))->toEqualCanonicalizing(builtInRules());
});

it('adds host custom rules to the built-ins without duplicates', function () {
    $rules = mergedRules(['rules' => [HostExtraRule::class]]);

    expect($rules)->toContain(HostExtraRule::class, ...builtInRules())
        ->and(count($rules))->toBe(9);
});

it('removes disabled rules only', function () {
    $rules = mergedRules(['disabled' => [HighRiskConfirmRule::class]]);

    expect($rules)->not->toContain(HighRiskConfirmRule::class)
        ->and(count($rules))->toBe(7);
});

it('stays fail-closed when the host config has no rules key', function () {
    $owner = makeOwner();
    mergedRules(['min_owners' => 1]);
    app()->forgetInstance(Guardrails::class);

    try {
        $owner->removeAccessRole('owner');
        $this->fail('last owner guardrail did not fire');
    } catch (AccessException $e) {
        expect($e->errorCode)->toBe('guardrail.last_owner');
    }
});

it('pins the default rule order', function () {
    expect(config('roles.guardrails.rules'))->toBe(builtInRules());
});

it('evaluates escalation before high-risk confirmation for a non-owner actor', function () {
    $actor = makeUser('actor@example.test');
    makeRole('support', ['tickets.view'])->users()->attach($actor);
    makePermission('orders.refund', 'high');
    makeRole('refunds', ['orders.refund']);
    $target = makeUser('target@example.test');

    expect(guardrailCode(new Operation(
        OperationType::AssignRole,
        actor: $actor->fresh(),
        target: $target,
        added: [Role::findByName('refunds', 'web')],
    )))->toBe('guardrail.escalation');
});
