<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;

it('requires confirmation for high-risk roles', function () {
    makePermission('x.danger', 'high');
    $role = makeRole('risky', ['x.danger']);
    $user = makeUser();

    expect(guardrailCode(new Operation(T::AssignRole, target: $user, added: [$role])))->toBe('guardrail.high_risk_confirm_required')
        ->and(guardrailCode(new Operation(T::AssignRole, target: $user, added: [$role], confirmed: true)))->toBeNull();
});

it('treats the owner role as high risk', function () {
    $role = makeRole('owner', [], ['is_system' => true]);

    expect(guardrailCode(new Operation(T::AssignRole, target: makeUser(), added: [$role])))->toBe('guardrail.high_risk_confirm_required');
});

it('ignores low-risk roles and respects the toggle', function () {
    makePermission('x.view');
    $safe = makeRole('safe', ['x.view']);
    makePermission('x.danger', 'high');
    $risky = makeRole('risky', ['x.danger']);

    $user = makeUser();

    expect(guardrailCode(new Operation(T::AssignRole, target: $user, added: [$safe])))->toBeNull();

    config(['roles.guardrails.confirm_high_risk' => false]);
    expect(guardrailCode(new Operation(T::AssignRole, target: $user, added: [$risky])))->toBeNull();
});
