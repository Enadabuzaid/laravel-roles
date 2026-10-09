<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Listeners;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Auth\Events\Validated;
use Illuminate\Validation\ValidationException;

/**
 * Credentials were valid: refuse the sign-in when the user's access state does not allow it.
 * Users without a state row (and users whose model is not an AccessUser) pass.
 */
final class BlockInactiveUser
{
    public function handle(Validated $event): void
    {
        $user = $event->user;

        if (! $user instanceof AccessUser) {
            return;
        }

        $state = UserAccessState::query()->where('user_id', $user->getAuthIdentifier())->first();

        if ($state === null || $state->canSignInNow()) {
            return;
        }

        throw ValidationException::withMessages([
            (string) config('fortify.username', 'email') => [(string) trans('access::auth.inactive')],
        ]);
    }
}
