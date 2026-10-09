<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether a user's access state forbids signing in. Fails OPEN: without the users module or its
 * `user_access_states` table everyone is active (the doctor `users-state` check reports that state), so a
 * missing migration can never lock everybody out. Only a positive table check is memoized (a table created later, for example by a migration run inside a long-lived worker, is picked up on the next check); a missing table is re-checked each time.
 */
final class SignInGate
{
    private ?bool $tableReady = null; // true once seen; never stores false

    public function blocks(mixed $user): bool
    {
        return $user instanceof AccessUser && $user instanceof Authenticatable && $this->blocksIdentifier($user->getAuthIdentifier());
    }

    public function blocksIdentifier(mixed $identifier): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $state = UserAccessState::query()->where('user_id', $identifier)->first();

        return $state !== null && ! $state->canSignInNow();
    }

    public function enabled(): bool
    {
        if (! config('roles.modules.users')) {
            return false;
        }

        if ($this->tableReady === true) {
            return true;
        }

        if (Schema::hasTable((new UserAccessState)->getTable())) {
            $this->tableReady = true;

            return true;
        }

        return false;
    }
}
