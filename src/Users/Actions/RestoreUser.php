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
use Enadstack\LaravelRoles\Users\Support\UserLookup;
use Illuminate\Database\Eloquent\Model;

/**
 * Brings a trashed user back with the roles and access state they had when they were trashed. Refused (409) for
 * a user that is not in the trash and when another account has taken the address meanwhile.
 */
final class RestoreUser extends UserLifecycleAction
{
    public function handle(Model $user): Model
    {
        app(ActorContext::class)->assertKnownActor();

        return $this->transaction(function () use ($user): Model {
            $target = $this->lockTarget($user, withTrashed: true);

            if (! $this->isTrashed($target)) {
                throw new AccessException((string) trans('access::user.not_trashed'), 'user.not_trashed', 409);
            }

            app(Guardrails::class)->assert(new Operation(OperationType::RestoreUser, actor: app(ActorContext::class)->actor(), target: $target));

            $email = $target->getAttribute('email');
            if (is_string($email) && $email !== '' && UserLookup::emailTaken($email, ignoring: $target->getKey())) {
                throw new AccessException((string) trans('access::user.email_taken'), 'user.email_taken', 409);
            }

            if (method_exists($target, 'restore')) {
                $target->restore();
            }

            app(AuditRecorder::class)->record('users', 'restored', $target, null, null, [], Severity::Info);

            return $target;
        });
    }
}
