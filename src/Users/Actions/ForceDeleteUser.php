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
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Support\InvitationSweeper;
use Enadstack\LaravelRoles\Users\Support\UserDeletionGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permanently deletes a TRASHED user (409 for a live one) after the actor typed their email, with the same access
 * row clean-up as the hard delete: pivots, state row, saved views and `invited_by` references. A host foreign key
 * that still points at the user answers 409 user.in_use and changes nothing.
 */
final class ForceDeleteUser extends UserLifecycleAction
{
    public function handle(Model $user, ?string $confirmation): void
    {
        app(ActorContext::class)->assertKnownActor();

        try {
            $this->transaction(function () use ($user, $confirmation): void {
                $target = $this->lockTarget($user, withTrashed: true);

                if (! $this->isTrashed($target)) {
                    throw new AccessException((string) trans('access::user.not_trashed'), 'user.not_trashed', 409);
                }

                app(Guardrails::class)->assert(new Operation(
                    OperationType::ForceDeleteUser,
                    actor: app(ActorContext::class)->actor(),
                    target: $target,
                    confirmation: $confirmation,
                ));

                $old = [
                    'id' => $target->getKey(),
                    'name' => $target->getAttribute('name'),
                    'email' => $target->getAttribute('email'),
                ];
                $isOwner = app(OwnerCounter::class)->isOwner($target);

                $swept = app(InvitationSweeper::class)->revokeAllPending($target);
                $this->removeAccessRows($target);

                app(UserDeletionGuard::class)->viaAction(static fn () => $target->forceDelete());

                app(AuditRecorder::class)->record('users', 'force_deleted', null, $old, null, ['was_owner' => $isOwner] + InvitationSweeper::auditMeta($swept), Severity::Critical);
            });
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw new AccessException((string) trans('access::user.in_use'), 'user.in_use', 409);
            }

            throw $e;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
