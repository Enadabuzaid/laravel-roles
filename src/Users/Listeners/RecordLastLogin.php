<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Listeners;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Users\Actions\RecordLastLogin as RecordLastLoginAction;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Records a successful sign-in. Registered after BlockInactiveUser, whose refusal throws before this runs, so a
 * refused user never gets a last login. It never throws into the login flow: failures are reported only.
 */
final class RecordLastLogin
{
    public function __construct(private readonly RecordLastLoginAction $action) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof AccessUser || ! $user instanceof Model) {
            return;
        }

        try {
            $this->action->handle($user, request()->ip());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
