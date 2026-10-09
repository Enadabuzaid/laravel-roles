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
 * missing migration can never lock everybody out. The table check is memoized per container lifetime.
 */
final class SignInGate
{
    private ?bool $tableReady = null;

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

    private function enabled(): bool
    {
        if (! config('roles.modules.users')) {
            return false;
        }

        return $this->tableReady ??= Schema::hasTable((new UserAccessState)->getTable());
    }
}
