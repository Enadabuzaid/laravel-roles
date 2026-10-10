<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Closure;
use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Severity;
use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Model;

/**
 * Backstop for deletes that bypass the DeleteUser Action (a host calling `$user->delete()` directly, for example
 * an "delete my account" page). Registered by the HasAccess trait on the `deleting` / `deleted` model events:
 *
 * - `deleting`: refuses (LastOwnerDeletionException, fail-closed) when the user is an active owner and fewer than
 *   `min_owners` active owners would remain. The check is not serialized by a lock (the model event runs outside
 *   any transaction); use the Action for locked, guardrailed deletes.
 * - `deleted`: removes the user's state row and saved views, revokes the invitations they sent and writes ONE
 *   `users.deleted` audit entry. A soft delete (SoftDeletes hosts) keeps those rows, ends the sessions and writes
 *   ONE `users.trashed` entry instead.
 *
 * The Action marks its own delete with viaAction(), so nothing is done twice. Query builder deletes and
 * `withoutEvents()` bypass model events by nature. Inactive when `roles.modules.users` is off.
 */
final class UserDeletionGuard
{
    private int $viaAction = 0;

    /** @var array<string, array{old: array<string, mixed>, was_owner: bool}> */
    private array $pending = [];

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function viaAction(Closure $callback): mixed
    {
        $this->viaAction++;

        try {
            return $callback();
        } finally {
            $this->viaAction--;
        }
    }

    public function deleting(Model $user): void
    {
        if ($this->viaAction > 0 || ! config('roles.modules.users')) {
            return;
        }

        $owners = app(OwnerCounter::class);
        $wasOwner = $owners->isOwner($user);

        if ($owners->isActiveOwner($user) && $owners->count() - 1 < (int) config('roles.guardrails.min_owners', 1)) {
            throw new LastOwnerDeletionException;
        }

        $this->pending[$this->key($user)] = [
            'old' => ['id' => $user->getKey(), 'name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email')],
            'was_owner' => $wasOwner,
        ];
    }

    public function deleted(Model $user): void
    {
        $key = $this->key($user);
        $context = $this->pending[$key] ?? null;
        unset($this->pending[$key]);

        if ($context === null || $this->viaAction > 0) {
            return;
        }

        $sweeper = app(InvitationSweeper::class);
        $swept = $sweeper->revokeAllPending($user);

        if (method_exists($user, 'isForceDeleting') && ! $user->isForceDeleting()) {
            // Moved to the trash: roles and state stay so a restore brings the access back; only the sessions end.
            app(SessionRevoker::class)->revoke($user);
            app(AuditRecorder::class)->record(
                'users',
                'trashed',
                $user,
                null,
                null,
                ['was_owner' => $context['was_owner'], 'via' => 'model'] + InvitationSweeper::auditMeta($swept),
                $context['was_owner'] ? Severity::Critical : Severity::Warning,
            );

            return;
        }

        UserAccessState::query()->where('user_id', $user->getKey())->delete();
        SavedView::query()->where('user_id', $user->getKey())->delete();
        AccessInvitation::query()->where('invited_by', $user->getKey())->update(['invited_by' => null]);
        UserAccessState::query()->where('invited_by', $user->getKey())->update(['invited_by' => null]);

        app(AuditRecorder::class)->record(
            'users',
            'deleted',
            null,
            $context['old'],
            null,
            ['was_owner' => $context['was_owner'], 'via' => 'model'] + InvitationSweeper::auditMeta($swept),
            Severity::Critical,
        );
    }

    private function key(Model $user): string
    {
        return $user::class.'#'.$user->getKey();
    }
}
