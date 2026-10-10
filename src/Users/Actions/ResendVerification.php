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
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Sends the host's email verification notification again. Needs a MustVerifyEmail host (409
 * user.verification_unsupported) and an unverified user (409 user.already_verified); at most 3 per 10 minutes per
 * target (429 too_many_requests with `errors.retry_after` seconds). Changes no state, so there is no state event.
 */
final class ResendVerification extends UserLifecycleAction
{
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 600;

    /** @return bool whether the notification was handed to the host's notifier */
    public function handle(Model $user): bool
    {
        app(ActorContext::class)->assertKnownActor();

        $target = $this->transaction(function () use ($user): MustVerifyEmail&Model {
            $target = $this->lockTarget($user);

            app(Guardrails::class)->assert(new Operation(OperationType::UpdateUser, actor: app(ActorContext::class)->actor(), target: $target));

            if (! $target instanceof MustVerifyEmail) {
                throw new AccessException((string) trans('access::user.verification_unsupported'), 'user.verification_unsupported', 409);
            }

            if ($target->hasVerifiedEmail()) {
                throw new AccessException((string) trans('access::user.already_verified'), 'user.already_verified', 409);
            }

            return $target;
        });

        $key = 'access-verify:'.$target->getKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new AccessException(
                (string) trans('access::user.verification_throttled'),
                'too_many_requests',
                429,
                ['retry_after' => [RateLimiter::availableIn($key)]],
            );
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        $sent = false;
        try {
            $target->sendEmailVerificationNotification();
            $sent = true;
        } catch (Throwable $e) {
            report($e);
        }

        app(AuditRecorder::class)->record('users', 'verification_resent', $target, null, null, ['sent' => $sent], Severity::Info);

        return $sent;
    }
}
