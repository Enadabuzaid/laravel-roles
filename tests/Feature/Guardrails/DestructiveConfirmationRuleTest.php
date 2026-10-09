<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;

it('requires the exact name for force delete', function () {
    $role = makeRole('plain');

    expect(guardrailCode(new Operation(T::ForceDeleteRole, role: $role)))->toBe('guardrail.confirmation_mismatch')
        ->and(guardrailCode(new Operation(T::ForceDeleteRole, role: $role, confirmation: 'nope')))->toBe('guardrail.confirmation_mismatch')
        ->and(guardrailCode(new Operation(T::ForceDeleteRole, role: $role, confirmation: 'plain')))->toBeNull();
});

it('requires the name to delete a role with members only', function () {
    $empty = makeRole('empty');
    $busy = makeRole('busy');
    makeUser()->assignRole('busy');

    expect(guardrailCode(new Operation(T::DeleteRole, role: $empty)))->toBeNull()
        ->and(guardrailCode(new Operation(T::DeleteRole, role: $busy)))->toBe('guardrail.confirmation_mismatch')
        ->and(guardrailCode(new Operation(T::DeleteRole, role: $busy, confirmation: 'busy')))->toBeNull();
});

it('requires the target email to delete a user', function () {
    $user = makeUser('gone@example.test');

    expect(guardrailCode(new Operation(T::DeleteUser, target: $user)))->toBe('guardrail.confirmation_mismatch')
        ->and(guardrailCode(new Operation(T::DeleteUser, target: $user, confirmation: 'gone@example.test')))->toBeNull();
});

it('is skipped when the toggle is off', function () {
    config(['roles.guardrails.type_to_confirm_destructive' => false]);

    expect(guardrailCode(new Operation(T::ForceDeleteRole, role: makeRole('plain'))))->toBeNull();
});

it('treats an empty expected email as a mismatch, even with an empty confirmation', function () {
    $user = makeUser('');

    expect(guardrailCode(new Operation(T::DeleteUser, target: $user)))->toBe('guardrail.confirmation_mismatch')
        ->and(guardrailCode(new Operation(T::DeleteUser, target: $user, confirmation: '')))->toBe('guardrail.confirmation_mismatch');
});
