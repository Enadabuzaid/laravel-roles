<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * An administrator vouches for the member's email address: `email_verified_at` is set and a user waiting for
 * verification becomes active. Already verified is a 409.
 */
final class VerifyUserEmail extends UserLifecycleAction
{
    public function handle(Model $user): void
    {
        app(ActorContext::class)->assertKnownActor();

        $this->transaction(function () use ($user): void {
            $target = $this->lockTarget($user);

            app(Guardrails::class)->assert(new Operation(OperationType::UpdateUser, actor: app(ActorContext::class)->actor(), target: $target));

            if ($target->getAttribute('email_verified_at') !== null) {
                throw new AccessException((string) trans('access::user.already_verified'), 'user.already_verified', 409);
            }

            $target->forceFill(['email_verified_at' => now()])->save();

            $state = $this->stateOf($target);
            $current = $this->statusOf($state);
            $new = ['email_verified_at' => $target->getAttribute('email_verified_at')];
            $old = ['email_verified_at' => null];

            if ($state !== null && $current === UserStatus::PendingVerification) {
                $this->writeState($target, $state, ['status' => UserStatus::Active->value, 'activated_at' => $state->activated_at ?? now()]);
                $old['status'] = $current->value;
                $new['status'] = UserStatus::Active->value;
            }

            app(AuditRecorder::class)->record('users', 'email_verified', $target, $old, $new, [], Severity::Warning);
        });
    }
}
