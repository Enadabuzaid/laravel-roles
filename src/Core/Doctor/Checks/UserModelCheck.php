<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Concerns\HasAccess;
use Enadstack\LaravelRoles\Contracts\AccessUser;
use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;

final class UserModelCheck implements Check
{
    public function name(): string
    {
        return 'user-model';
    }

    public function run(): CheckResult
    {
        $model = config('roles.user_model');

        if (! is_string($model) || ! class_exists($model)) {
            return CheckResult::fail('roles.user_model is not an existing class: '.(is_string($model) ? $model : gettype($model)));
        }

        if (! is_subclass_of($model, AccessUser::class)) {
            return CheckResult::fail("{$model} must implement ".AccessUser::class.'.');
        }

        if (! in_array(HasAccess::class, class_uses_recursive($model), true)) {
            return CheckResult::fail("{$model} must use the ".HasAccess::class.' trait.');
        }

        return CheckResult::pass("{$model} implements AccessUser and uses HasAccess.");
    }
}
