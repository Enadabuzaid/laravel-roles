<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\Violation;

final class DestructiveConfirmationRule implements Guardrail
{
    public function check(Operation $operation): void
    {
        if (! config('roles.guardrails.type_to_confirm_destructive', true)) {
            return;
        }

        $expected = $this->expectedConfirmation($operation);

        // An empty expectation (e.g. a user without an email) can never be satisfied: always a mismatch.
        if ($expected !== null && ($expected === '' || ! $this->matches($expected, $operation->confirmation))) {
            throw Violation::confirmationMismatch();
        }
    }

    private function matches(string $expected, ?string $given): bool
    {
        return $given !== null && mb_strtolower(trim($given)) === mb_strtolower(trim($expected));
    }

    private function expectedConfirmation(Operation $operation): ?string
    {
        $role = $operation->role;

        return match ($operation->type) {
            OperationType::ForceDeleteRole => $role?->name,
            OperationType::DeleteRole => $role !== null && $role->users()->count() > 0 ? $role->name : null,
            OperationType::DeleteUser => $operation->target === null ? null : (string) $operation->target->getAttribute('email'),
            default => null,
        };
    }
}
