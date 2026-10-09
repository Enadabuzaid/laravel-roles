<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Suspended or deactivated becomes active again (roles were never touched, so access returns as it was).
 * An active user is an invalid transition (409); a locked user needs `unlock`.
 */
final class ReactivateUser extends UserLifecycleAction
{
    /** @return bool whether anything changed (always true: an active user is refused, not ignored) */
    public function handle(Model $user): bool
    {
        app(ActorContext::class)->assertKnownActor();

        return $this->transaction(function () use ($user): bool {
            $target = $this->lockTarget($user);
            $state = $this->stateOf($target);
            $current = $this->statusOf($state);

            app(Guardrails::class)->assert(new Operation(OperationType::ReactivateUser, actor: app(ActorContext::class)->actor(), target: $target));

            if (! in_array($current, [UserStatus::Suspended, UserStatus::Deactivated], true)) {
                throw $this->invalidTransition($current, 'reactivated');
            }

            $this->writeState($target, $state, ['status' => UserStatus::Active->value, 'status_reason' => null, 'activated_at' => now()]);

            app(AuditRecorder::class)->record('users', 'reactivated', $target, ['status' => $current->value], ['status' => UserStatus::Active->value]);

            return true;
        });
    }
}
