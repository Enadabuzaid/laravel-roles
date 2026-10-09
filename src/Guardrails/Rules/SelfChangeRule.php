<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\Violation;

final class SelfChangeRule implements Guardrail
{
    public function check(Operation $operation): void
    {
        if (! config('roles.guardrails.prevent_self_escalation', true)) {
            return;
        }

        if ($operation->type->isRoleChange() && $operation->actorIsTarget()) {
            throw Violation::selfRoleChange();
        }
    }
}
