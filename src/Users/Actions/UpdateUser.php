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
use Enadstack\LaravelRoles\Users\Support\HostUserColumns;
use Enadstack\LaravelRoles\Users\Support\UserLookup;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Edits a member's name and/or email. The email is normalized (trim, lowercase) and must be unique across live and
 * trashed users; changing it clears the verification and, for MustVerifyEmail hosts, sends a new verification mail
 * after commit. Changing your own email is refused (409): use the account settings. One audit entry with the changed
 * attributes (Warning when the email changed); a request that changes nothing writes nothing.
 */
final class UpdateUser extends UserLifecycleAction
{
    /**
     * @param  array{name?: string, email?: string}  $data
     * @return array{changed: list<string>, verification_sent: bool}
     */
    public function handle(Model $user, array $data): array
    {
        app(ActorContext::class)->assertKnownActor();

        try {
            $result = $this->transaction(function () use ($user, $data): array {
                $target = $this->lockTarget($user);
                $actor = app(ActorContext::class)->actor();

                app(Guardrails::class)->assert(new Operation(OperationType::UpdateUser, actor: $actor, target: $target));

                $old = [];
                $new = [];

                if (isset($data['name']) && $data['name'] !== (string) $target->getAttribute('name')) {
                    $old['name'] = $target->getAttribute('name');
                    $new['name'] = $data['name'];
                }

                $currentEmail = (string) $target->getAttribute('email');
                if (isset($data['email']) && UserLookup::normalize($data['email']) !== UserLookup::normalize($currentEmail)) {
                    if ($actor !== null && $actor->is($target)) {
                        throw new AccessException((string) trans('access::user.self_email_change'), 'user.self_email_change', 409);
                    }

                    if (UserLookup::emailTaken($data['email'], includeTrashed: true, ignoring: $target->getKey())) {
                        throw $this->emailTaken();
                    }

                    $old['email'] = $currentEmail;
                    $new['email'] = UserLookup::normalize($data['email']);
                }

                if ($new === []) {
                    return ['changed' => [], 'verification_sent' => false, 'target' => $target];
                }

                $attributes = $new;
                if (isset($new['email']) && app(HostUserColumns::class)->has($target, 'email_verified_at')) {
                    $attributes['email_verified_at'] = null;
                }

                $target->forceFill($attributes)->save();

                app(AuditRecorder::class)->record(
                    'users',
                    'updated',
                    $target,
                    $old,
                    $new,
                    [],
                    isset($new['email']) ? Severity::Warning : Severity::Info,
                );

                return ['changed' => array_keys($new), 'verification_sent' => false, 'target' => $target];
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw $this->emailTaken();
            }

            throw $e;
        }

        $target = $result['target'];
        $sent = false;

        if (in_array('email', $result['changed'], true) && $target instanceof MustVerifyEmail) {
            try {
                $target->sendEmailVerificationNotification();
                $sent = true;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return ['changed' => $result['changed'], 'verification_sent' => $sent];
    }

    private function emailTaken(): ValidationException
    {
        return ValidationException::withMessages(['email' => [(string) trans('validation.unique', ['attribute' => 'email'])]]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $info = $e->errorInfo ?? [];

        return ($info[0] ?? null) === '23505'
            || ($info[1] ?? null) === 1062
            || str_contains(strtoupper($e->getMessage()), 'UNIQUE CONSTRAINT');
    }
}
