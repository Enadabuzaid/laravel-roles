<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;

it('blocks changing own role', function (T $type) {
    $user = makeUser();

    expect(guardrailCode(new Operation($type, actor: $user, target: $user, confirmation: $user->email)))->toBe('guardrail.self_role_change');
})->with([T::AssignRole, T::RemoveRole, T::SyncRoles]);

it('allows changing another user, system actor, or with the toggle off', function () {
    $a = makeUser();
    $b = makeUser('b@example.test');

    expect(guardrailCode(new Operation(T::AssignRole, actor: $a, target: $b)))->toBeNull()
        ->and(guardrailCode(new Operation(T::AssignRole, actor: null, target: $a)))->toBeNull();

    config(['roles.guardrails.prevent_self_escalation' => false]);
    expect(guardrailCode(new Operation(T::AssignRole, actor: $a, target: $a)))->toBeNull();
});
