<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Guardrails\OwnerCounter;
use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\SessionRevoker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    protected function lockTarget(Model $user): Model
    {
        app(OwnerCounter::class)->lockSuperRole();

        $fresh = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->first();

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
            "A {$current->value} user can't be {$action}.",
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
}
