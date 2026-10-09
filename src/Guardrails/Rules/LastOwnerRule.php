<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Roles\Support\RoleRisk;

final class LastOwnerRule implements Guardrail
{
    public function __construct(private readonly OwnerCounter $owners) {}

    public function check(Operation $operation): void
    {
        $target = $operation->target;
        if ($target === null || ! $this->affectsOwnership($operation)) {
            return;
        }

        // Only an ACTIVE owner leaving (or losing the role) lowers the count; an inactive owner never counted.
        if (! $this->owners->isActiveOwner($target)) {
            return;
        }

        if ($this->owners->count() - 1 < (int) config('roles.guardrails.min_owners', 1)) {
            throw Violation::lastOwner();
        }
    }

    private function affectsOwnership(Operation $operation): bool
    {
        if ($operation->type->isUserDestructive()) {
            return true;
        }

        foreach ($operation->removed as $role) {
            if (RoleRisk::isOwnerRole($role)) {
                return true;
            }
        }

        return false;
    }
}
