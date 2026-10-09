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
use Enadstack\LaravelRoles\Users\Support\InvitationSweeper;
use Illuminate\Database\Eloquent\Model;

/** Active, locked or suspended becomes deactivated; already deactivated is a no-op; anything else is an invalid transition. */
final class DeactivateUser extends UserLifecycleAction
{
    /** @return bool whether anything changed */
    public function handle(Model $user, string $reason): bool
    {
        app(ActorContext::class)->assertKnownActor();

        return $this->transaction(function () use ($user, $reason): bool {
            $target = $this->lockTarget($user);
            $state = $this->stateOf($target);
            $current = $this->statusOf($state);

            app(Guardrails::class)->assert(new Operation(OperationType::DeactivateUser, actor: app(ActorContext::class)->actor(), target: $target));

            if ($current === UserStatus::Deactivated) {
                return false;
            }

            if (! in_array($current, [UserStatus::Active, UserStatus::Locked, UserStatus::Suspended], true)) {
                throw $this->invalidTransition($current, 'deactivated');
            }

            $this->writeState($target, $state, ['status' => UserStatus::Deactivated->value, 'status_reason' => $reason]);
            $this->revokeAccessAfterCommit($target);
            $swept = app(InvitationSweeper::class)->revokeAllPending($target);

            app(AuditRecorder::class)->record(
                'users',
                'deactivated',
                $target,
                ['status' => $current->value],
                ['status' => UserStatus::Deactivated->value],
                ['reason' => $reason] + InvitationSweeper::auditMeta($swept),
                app(OwnerCounter::class)->isOwner($target) ? Severity::Critical : Severity::Warning,
            );

            return true;
        });
    }
}
