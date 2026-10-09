<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Guardrails\Rules;

use Enadstack\LaravelRoles\Guardrails\Guardrail;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Guardrails\Violation;
use Illuminate\Database\Eloquent\Model;

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

    public function __construct(private readonly OwnerCounter $owners) {}

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

        if ($this->owners->isOwner($actor)) {
            return;
        }

        if ($this->owners->isOwner($target)) {
            throw Violation::targetAboveActor();
        }

        $held = array_fill_keys($this->permissionNames($actor), true);

        foreach ($this->permissionNames($target) as $name) {
            if (! isset($held[$name])) {
                throw Violation::targetAboveActor();
            }
        }
    }

    /** @return list<string> */
    private function permissionNames(Model $user): array
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        /** @var list<string> */
        return array_values($user->getAllPermissions()
            ->where('guard_name', (string) config('roles.guard'))
            ->pluck('name')
            ->all());
    }
}
