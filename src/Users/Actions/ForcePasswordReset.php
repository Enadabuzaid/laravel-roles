<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Support\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Sends the host's password reset link to the user. No token is ever returned or logged, a mail failure
 * does not undo the audit entry (the response says whether the link went out) and `password_changed_at` is untouched.
 */
final class ForcePasswordReset extends UserLifecycleAction
{
    /** @return bool whether the reset link was sent */
    public function handle(Model $user): bool
    {
        app(ActorContext::class)->assertKnownActor();

        $email = $this->transaction(function () use ($user): string {
            $target = $this->lockTarget($user);

            app(Guardrails::class)->assert(new Operation(OperationType::ForcePasswordReset, actor: app(ActorContext::class)->actor(), target: $target));
            app(AuditRecorder::class)->record('users', 'password_reset_forced', $target);

            return (string) $target->getAttribute('email');
        });

        return $this->sendLink($email);
    }

    private function sendLink(string $email): bool
    {
        if ($email === '') {
            return false;
        }

        try {
            return Password::broker()->sendResetLink(['email' => $email]) === Password::RESET_LINK_SENT;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
