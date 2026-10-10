<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Users\Support\TargetStanding;

/**
 * A non-owner may not manage (suspend, reactivate, deactivate, delete, unlock, force a password reset for)
 * an owner or a user whose effective permissions exceed their own; the same goes for removing roles from such a user
 * (remove, or a sync that removes something; assign-only changes are unaffected). The system actor and owners skip it.
 */
final class TargetAboveActorRule implements Guardrail
{
    /** @var list<OperationType> */
    private const TARGET_OPERATIONS = [
        OperationType::SuspendUser,
        OperationType::DeactivateUser,
        OperationType::DeleteUser,
        OperationType::ReactivateUser,
        OperationType::UnlockUser,
        OperationType::ForcePasswordReset,
    ];

    public function __construct(private readonly TargetStanding $standing) {}

    public function check(Operation $operation): void
    {
        $actor = $operation->actor;
        $target = $operation->target;

        if ($actor === null || $target === null || ! config('roles.guardrails.prevent_target_above_actor', true)) {
            return;
        }

        $isRoleRemoval = in_array($operation->type, [OperationType::RemoveRole, OperationType::SyncRoles], true) && $operation->removed !== [];

        if ((! $isRoleRemoval && ! in_array($operation->type, self::TARGET_OPERATIONS, true)) || $operation->actorIsTarget()) {
            return;
        }

        if (in_array($this->standing->reasonFor($actor, $target), ['owner', 'above'], true)) {
            throw Violation::targetAboveActor();
        }
    }
}
