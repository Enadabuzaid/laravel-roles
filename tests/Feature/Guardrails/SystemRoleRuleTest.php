<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;

it('blocks deleting system roles', function (T $type) {
    $role = makeRole('locked', [], ['is_system' => true]);

    expect(guardrailCode(new Operation($type, role: $role)))->toBe('guardrail.system_role');
})->with([T::DeleteRole, T::ForceDeleteRole]);

it('protects the super role even when is_system is false', function (T $type) {
    $role = makeRole('owner', [], ['is_system' => false]);

    expect(guardrailCode(new Operation($type, role: $role)))->toBe('guardrail.system_role');
})->with([T::DeleteRole, T::ForceDeleteRole]);

it('blocks renaming a system role but allows the same name', function () {
    $role = makeRole('locked', [], ['is_system' => true]);

    expect(guardrailCode(new Operation(T::RenameRole, role: $role, newName: 'other')))->toBe('guardrail.system_role')
        ->and(guardrailCode(new Operation(T::RenameRole, role: $role, newName: 'locked')))->toBeNull();
});

it('allows ordinary roles', function () {
    $role = makeRole('plain');

    expect(guardrailCode(new Operation(T::DeleteRole, role: $role)))->toBeNull()
        ->and(guardrailCode(new Operation(T::RenameRole, role: $role, newName: 'renamed')))->toBeNull();
});
