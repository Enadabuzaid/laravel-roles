<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType as T;

it('blocks suspending, deactivating or deleting yourself', function (T $type) {
    $user = makeUser();

    expect(guardrailCode(new Operation($type, actor: $user, target: $user, confirmation: $user->email)))->toBe('guardrail.self_destructive');
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);

it('allows others, system actor, and toggle off', function (T $type) {
    $a = makeUser();
    $b = makeUser('b@example.test');

    expect(guardrailCode(new Operation($type, actor: $a, target: $b, confirmation: $b->email)))->toBeNull()
        ->and(guardrailCode(new Operation($type, actor: null, target: $a, confirmation: $a->email)))->toBeNull();

    config(['roles.guardrails.prevent_self_escalation' => false]);
    expect(guardrailCode(new Operation($type, actor: $a, target: $a, confirmation: $a->email)))->toBeNull();
})->with([T::SuspendUser, T::DeactivateUser, T::DeleteUser]);
