<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Guardrails\Guardrails;
use Enadstack\LaravelRoles\Guardrails\Operation;
use Enadstack\LaravelRoles\Guardrails\OperationType;
use Enadstack\LaravelRoles\Support\ActorContext;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Ends the user's current access (sessions and tokens, like suspend) and sends the host's password reset link.
 * It does NOT change the password: the old password keeps working until the user follows the link. No token is
 * ever returned or logged. `password_changed_at` on an existing state row is cleared. The broker status is
 * audited and returned; a throttled or failed send does not undo the revocation.
 */
final class ForcePasswordReset extends UserLifecycleAction
{
    public const MAIL_FAILED = 'mail_failed';

    /** @return array{link_sent: bool, broker_status: string} */
    public function handle(Model $user): array
    {
        app(ActorContext::class)->assertKnownActor();

        $target = $this->transaction(function () use ($user): Model {
            $target = $this->lockTarget($user);

            app(Guardrails::class)->assert(new Operation(OperationType::ForcePasswordReset, actor: app(ActorContext::class)->actor(), target: $target));

            UserAccessState::query()->where('user_id', $target->getKey())->update(['password_changed_at' => null]);
            $this->revokeAccessAfterCommit($target);

            return $target;
        });

        $status = $this->sendLink((string) $target->getAttribute('email'));
        app(AuditRecorder::class)->record('users', 'password_reset_forced', $target, null, null, ['broker_status' => $status]);

        return ['link_sent' => $status === Password::RESET_LINK_SENT, 'broker_status' => $status];
    }

    private function sendLink(string $email): string
    {
        if ($email === '') {
            return Password::INVALID_USER;
        }

        try {
            return $this->broker()->sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            report($e);

            return self::MAIL_FAILED;
        }
    }

    /** The password broker whose provider is the one the package guard authenticates against; the default broker otherwise. */
    private function broker(): PasswordBroker
    {
        $provider = config('auth.guards.'.config('roles.guard').'.provider');

        if (is_string($provider)) {
            foreach ((array) config('auth.passwords') as $name => $broker) {
                if (is_array($broker) && ($broker['provider'] ?? null) === $provider) {
                    return Password::broker((string) $name);
                }
            }
        }

        return Password::broker();
    }
}
