<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class UsersStateCheck implements Check
{
    public function name(): string
    {
        return 'users-state';
    }

    public function run(): CheckResult
    {
        if (! config('roles.modules.users')) {
            return CheckResult::pass('Users module disabled.');
        }

        if (! Schema::hasTable('user_access_states')) {
            return CheckResult::fail('The user_access_states table is missing — run roles:install then php artisan migrate.');
        }

        $model = config('roles.user_model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return CheckResult::pass('User model not configured; state rows not checked.');
        }

        $missing = $model::query()
            ->whereNotIn((new $model)->getQualifiedKeyName(), UserAccessState::query()->select('user_id'))
            ->count();

        return $missing > 0
            ? CheckResult::warn("{$missing} user(s) have no access state row (treated as active). Run php artisan roles:backfill-users.")
            : CheckResult::pass('Every user has an access state row.');
    }
}
