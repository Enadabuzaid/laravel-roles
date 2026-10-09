<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Enadstack\LaravelRoles\Users\Support\TargetStanding;

/**
 * A non-owner may not manage (suspend, deactivate, delete, unlock, force a password reset for)
 * an owner or a user whose effective permissions exceed their own. The system actor and owners skip it.
 */
final class TargetAboveActorRule implements Guardrail
{
    /**
     * Operation type values this rule applies to. Matching by value lets the lifecycle operations that
     * are added later (`unlock_user`, `force_password_reset`) be covered as soon as the cases exist.
     *
     * @var list<string>
     */
    private const TARGET_OPERATIONS = [
        'suspend_user',
        'deactivate_user',
        'delete_user',
        'unlock_user',
        'force_password_reset',
    ];

    public function __construct(private readonly TargetStanding $standing) {}

    public function check(Operation $operation): void
    {
        $actor = $operation->actor;
        $target = $operation->target;

        if ($actor === null || $target === null || ! config('roles.guardrails.prevent_target_above_actor', true)) {
            return;
        }

        if (! in_array($operation->type->value, self::TARGET_OPERATIONS, true) || $operation->actorIsTarget()) {
            return;
        }

        if (in_array($this->standing->reasonFor($actor, $target), ['owner', 'above'], true)) {
            throw Violation::targetAboveActor();
        }
    }
}
