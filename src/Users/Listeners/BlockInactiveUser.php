<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Listeners;

use Enadstack\LaravelRoles\Users\Support\SignInGate;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Validated;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Refuses sign-ins of users whose access state does not allow them. Two hooks cover the different ways in:
 * `Validated` (credentials checked by `attempt()`/`validate()`, before anything is persisted) and `Login`
 * (everything that logs a user in without that check: Fortify's two-factor completion, `Auth::login()`,
 * `loginUsingId()`, remember-me recall). A `Validated` refusal throws before `Login` can fire, so the message
 * is never doubled. Users without a state row, non-AccessUser models and a missing users table pass (fail open).
 */
final class BlockInactiveUser
{
    public function __construct(private readonly SignInGate $gate) {}

    public function handle(Validated $event): void
    {
        if ($this->gate->blocks($event->user)) {
            throw $this->refusal();
        }
    }

    public function onLogin(Login $event): void
    {
        if (! $this->gate->blocks($event->user)) {
            return;
        }

        $guard = Auth::guard($event->guard);

        if ($guard instanceof SessionGuard) {
            $guard->logout();
            $guard->getSession()->invalidate();
        }

        throw $this->refusal();
    }

    private function refusal(): ValidationException
    {
        return ValidationException::withMessages([
            (string) config('fortify.username', 'email') => [(string) trans('access::auth.inactive')],
        ]);
    }
}
