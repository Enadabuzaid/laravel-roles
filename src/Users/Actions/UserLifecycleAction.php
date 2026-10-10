<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Models\SavedView;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\HostSoftDeletes;
use Enadstack\LaravelRoles\Users\Support\SessionRevoker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Shared steps of the user lifecycle Actions. Lock order inside the transaction: the super role, then the
 * target user, then its state row, so any two actions on the same user (or on owners) serialize.
 */
abstract class UserLifecycleAction extends Action
{
    /**
     * Locks the super role and the target user row and returns the user as it is now in the database.
     *
     * @throws ModelNotFoundException
     */
    protected function lockTarget(Model $user, bool $withTrashed = false): Model
    {
        app(OwnerCounter::class)->lockSuperRole();

        $query = $user->newQuery()->whereKey($user->getKey());
        if ($withTrashed && HostSoftDeletes::usedBy($user)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $fresh = $query->lockForUpdate()->first();

        return $fresh ?? throw (new ModelNotFoundException)->setModel($user::class, [$user->getKey()]);
    }

    protected function stateOf(Model $user): ?UserAccessState
    {
        return UserAccessState::query()->where('user_id', $user->getKey())->lockForUpdate()->first();
    }

    protected function statusOf(?UserAccessState $state): UserStatus
    {
        return $state === null ? UserStatus::Active : $state->status;
    }

    /** @param  array<string, mixed>  $attributes */
    protected function writeState(Model $user, ?UserAccessState $state, array $attributes): void
    {
        // A missing row is created with insert-or-ignore, then re-read under lock: a concurrent creator can't collide.
        ($state ?? UserAccessState::lockedFor($user->getKey()))->update($attributes);
    }

    protected function invalidTransition(UserStatus $current, string $action): AccessException
    {
        return new AccessException(
            (string) trans('access::errors.invalid_transition', [
                'status' => (string) trans('access::errors.status.'.$current->value),
                'action' => (string) trans('access::errors.action.'.$action),
            ]),
            'user.invalid_transition',
            409,
            ['status' => [$current->value]],
        );
    }

    /** Ends the user's sessions and tokens once the surrounding transaction has committed. */
    protected function revokeAccessAfterCommit(Model $user): void
    {
        DB::afterCommit(static function () use ($user): void {
            app(SessionRevoker::class)->revoke($user);
        });
    }

    /** The user is soft deleted right now (false for hosts without SoftDeletes). */
    protected function isTrashed(Model $user): bool
    {
        return method_exists($user, 'trashed') && (bool) $user->trashed();
    }

    /** Role and permission pivots of this model type, the state row, saved views and `invited_by` references of a user that is going away for good. */
    protected function removeAccessRows(Model $user): void
    {
        $this->detachPivots($user);
        UserAccessState::query()->where('user_id', $user->getKey())->delete();
        SavedView::query()->where('user_id', $user->getKey())->delete();
        AccessInvitation::query()->where('invited_by', $user->getKey())->update(['invited_by' => null]);
        UserAccessState::query()->where('invited_by', $user->getKey())->update(['invited_by' => null]);
    }

    /** Only rows of this model type: another model sharing the numeric id keeps its roles. */
    private function detachPivots(Model $user): void
    {
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $types = array_values(array_unique([$user->getMorphClass(), $user::class]));

        foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
            DB::table((string) config("permission.table_names.{$pivot}", $pivot))
                ->whereIn('model_type', $types)
                ->where($morphKey, $user->getKey())
                ->delete();
        }
    }

    /** Foreign key violations only: SQLSTATE 23503 (Postgres), MySQL 1451/1452, the SQLite message. Other integrity errors propagate. */
    protected function isForeignKeyViolation(QueryException $e): bool
    {
        $info = $e->errorInfo ?? [];

        return ($info[0] ?? null) === '23503'
            || in_array($info[1] ?? null, [1451, 1452], true)
            || str_contains(strtoupper($e->getMessage()), 'FOREIGN KEY');
    }
}
