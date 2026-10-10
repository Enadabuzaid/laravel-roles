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
use Enadstack\LaravelRoles\Users\Support\HostSoftDeletes;
use Enadstack\LaravelRoles\Users\Support\InvitationSweeper;
use Enadstack\LaravelRoles\Users\Support\UserDeletionGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deletes the user through the host model.
 *
 * - Host without SoftDeletes: the user row is deleted for good and the access rows that point at it are cleaned
 *   up: role and permission pivots of this model, its state row and saved views. Invitations and state rows it created
 *   are kept with `invited_by` set to null. The audit entry (`users.deleted`) has no subject (the row is gone) and
 *   carries the id, name and email as old attributes.
 * - Host with SoftDeletes: the user is moved to the trash. Roles, permissions and the access state are kept so a
 *   restore brings back exactly the access they had; sessions and tokens end and pending invitations are revoked.
 *   One `users.trashed` entry is recorded on the (still existing) user. ForceDeleteUser removes a trashed user.
 */
final class DeleteUser extends UserLifecycleAction
{
    public function handle(Model $user, ?string $confirmation): void
    {
        app(ActorContext::class)->assertKnownActor();

        try {
            $this->transaction(function () use ($user, $confirmation): void {
                $target = $this->lockTarget($user);

                app(Guardrails::class)->assert(new Operation(
                    OperationType::DeleteUser,
                    actor: app(ActorContext::class)->actor(),
                    target: $target,
                    confirmation: $confirmation,
                ));

                $isOwner = app(OwnerCounter::class)->isOwner($target);
                $swept = app(InvitationSweeper::class)->revokeAllPending($target);

                if (HostSoftDeletes::usedBy($target)) {
                    app(UserDeletionGuard::class)->viaAction(static fn () => $target->delete());
                    $this->revokeAccessAfterCommit($target);

                    app(AuditRecorder::class)->record('users', 'trashed', $target, null, null, ['was_owner' => $isOwner] + InvitationSweeper::auditMeta($swept), $isOwner ? Severity::Critical : Severity::Warning);

                    return;
                }

                $old = [
                    'id' => $target->getKey(),
                    'name' => $target->getAttribute('name'),
                    'email' => $target->getAttribute('email'),
                ];

                $this->removeAccessRows($target);

                app(UserDeletionGuard::class)->viaAction(static fn () => $target->delete());
                $this->revokeAccessAfterCommit($target);

                app(AuditRecorder::class)->record('users', 'deleted', null, $old, null, ['was_owner' => $isOwner] + InvitationSweeper::auditMeta($swept), Severity::Critical);
            });
        } catch (QueryException $e) {
            // A host foreign key still points at the user: nothing was deleted (the transaction rolled back).
            if ($this->isForeignKeyViolation($e)) {
                throw new AccessException((string) trans('access::user.in_use'), 'user.in_use', 409);
            }

            throw $e;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
