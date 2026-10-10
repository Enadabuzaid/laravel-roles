<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;

/** A locked user becomes active with the lockout cleared; any other status is an invalid transition (409). */
final class UnlockUser extends UserLifecycleAction
{
    /** @return bool whether anything changed (always true: a non-locked user is refused, not ignored) */
    public function handle(Model $user): bool
    {
        app(ActorContext::class)->assertKnownActor();

        return $this->transaction(function () use ($user): bool {
            $target = $this->lockTarget($user);
            $state = $this->stateOf($target);
            $current = $this->statusOf($state);

            app(Guardrails::class)->assert(new Operation(OperationType::UnlockUser, actor: app(ActorContext::class)->actor(), target: $target));

            if ($current !== UserStatus::Locked) {
                throw $this->invalidTransition($current, 'unlocked');
            }

            $this->writeState($target, $state, [
                'status' => UserStatus::Active->value,
                'status_reason' => null,
                'locked_until' => null,
                'failed_login_count' => 0,
            ]);

            app(AuditRecorder::class)->record('users', 'unlocked', $target, ['status' => $current->value], ['status' => UserStatus::Active->value], [], app(OwnerCounter::class)->isOwner($target) ? Severity::Critical : Severity::Info);

            return true;
        });
    }
}
