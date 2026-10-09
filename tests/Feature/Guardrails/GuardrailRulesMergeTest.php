<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Rules\DestructiveConfirmationRule;
use Enadstack\LaravelRoles\Guardrails\Rules\EscalationRule;
use Enadstack\LaravelRoles\Guardrails\Rules\HighRiskConfirmRule;
use Enadstack\LaravelRoles\Guardrails\Rules\LastOwnerRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfChangeRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SelfDestructiveRule;
use Enadstack\LaravelRoles\Guardrails\Rules\SystemRoleRule;
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
    return [SystemRoleRule::class, SelfChangeRule::class, SelfDestructiveRule::class, EscalationRule::class,
        LastOwnerRule::class, HighRiskConfirmRule::class, DestructiveConfirmationRule::class];
}

it('gives all built-in rules to a host config without rules', function () {
    expect(mergedRules(['min_owners' => 1]))->toEqualCanonicalizing(builtInRules());
});

it('adds host custom rules to the built-ins without duplicates', function () {
    $rules = mergedRules(['rules' => [HostExtraRule::class]]);

    expect($rules)->toContain(HostExtraRule::class, ...builtInRules())
        ->and(count($rules))->toBe(8);
});

it('removes disabled rules only', function () {
    $rules = mergedRules(['disabled' => [HighRiskConfirmRule::class]]);

    expect($rules)->not->toContain(HighRiskConfirmRule::class)
        ->and(count($rules))->toBe(6);
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
