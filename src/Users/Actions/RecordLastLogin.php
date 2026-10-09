<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Support\Action;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\SignInGate;
use Illuminate\Database\Eloquent\Model;

/** Stamps the last login time and IP on the user's state row and clears the failed-login counter. Not audited (high volume, no state change). */
final class RecordLastLogin extends Action
{
    public function handle(Model $user, ?string $ip): void
    {
        if (! app(SignInGate::class)->enabled()) {
            return;
        }

        $this->transaction(function () use ($user, $ip): void {
            UserAccessState::lockedFor($user->getKey())->update([
                'last_login_at' => now(),
                'last_login_ip' => $ip === null ? null : mb_substr($ip, 0, 45),
                'failed_login_count' => 0,
            ]);
        });
    }
}
