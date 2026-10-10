<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Listeners;

use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\SignInGate;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stamps `password_changed_at` on the user's state row when a password reset completes. A no-op without the users
 * module or its table; never throws into the reset flow.
 */
final class RecordPasswordChange
{
    public function __construct(private readonly SignInGate $gate) {}

    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if (! $user instanceof AccessUser || ! $user instanceof Model || ! $this->gate->enabled()) {
            return;
        }

        try {
            DB::transaction(static function () use ($user): void {
                UserAccessState::lockedFor($user->getKey())->update(['password_changed_at' => now()]);
            });
        } catch (Throwable $e) {
            report($e);
        }
    }
}
