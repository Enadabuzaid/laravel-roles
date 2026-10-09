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

/** Active or locked becomes suspended; already suspended is a no-op; anything else is an invalid transition. */
final class SuspendUser extends UserLifecycleAction
{
    /** @return bool whether anything changed */
    public function handle(Model $user, string $reason): bool
    {
        app(ActorContext::class)->assertKnownActor();

        return $this->transaction(function () use ($user, $reason): bool {
            $target = $this->lockTarget($user);
            $state = $this->stateOf($target);
            $current = $this->statusOf($state);

            app(Guardrails::class)->assert(new Operation(OperationType::SuspendUser, actor: app(ActorContext::class)->actor(), target: $target));

            if ($current === UserStatus::Suspended) {
                return false;
            }

            if (! in_array($current, [UserStatus::Active, UserStatus::Locked], true)) {
                throw $this->invalidTransition($current, 'suspended');
            }

            $this->writeState($target, $state, ['status' => UserStatus::Suspended->value, 'status_reason' => $reason]);
            $this->revokeAccessAfterCommit($target);
            $revokedInvitations = app(InvitationSweeper::class)->revokeAllPending($target);

            app(AuditRecorder::class)->record(
                'users',
                'suspended',
                $target,
                ['status' => $current->value],
                ['status' => UserStatus::Suspended->value],
                ['reason' => $reason] + ($revokedInvitations > 0 ? ['revoked_invitations' => $revokedInvitations] : []),
                app(OwnerCounter::class)->isOwner($target) ? Severity::Critical : Severity::Warning,
            );

            return true;
        });
    }
}
