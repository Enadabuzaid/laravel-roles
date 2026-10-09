<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Ends the server-side sessions and API tokens of a user. Both are optional (database session driver with a
 * `user_id` column; a `tokens()` relation such as Sanctum's) and best effort: a failure is reported and never
 * thrown, because the state change that triggered it is already committed and `access.active` still ends the session.
 */
final class SessionRevoker
{
    public function revoke(Model $user): void
    {
        $this->revokeSessions($user);
        $this->revokeTokens($user);
    }

    private function revokeSessions(Model $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        try {
            $table = (string) config('session.table', 'sessions');
            $connection = config('session.connection');
            $schema = Schema::connection(is_string($connection) ? $connection : null);

            if ($schema->hasTable($table) && $schema->hasColumn($table, 'user_id')) {
                DB::connection(is_string($connection) ? $connection : null)->table($table)->where('user_id', $user->getKey())->delete();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function revokeTokens(Model $user): void
    {
        if (! method_exists($user, 'tokens')) {
            return;
        }

        try {
            $tokens = $user->tokens();

            if (is_object($tokens) && method_exists($tokens, 'delete')) {
                $tokens->delete();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
