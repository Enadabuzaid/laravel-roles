<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Whether the host user model soft deletes. The users trash (trashed filter, restore, permanent delete) exists only
 * then; a host without SoftDeletes keeps the permanent delete behaviour and the trash endpoints answer 404.
 */
final class HostSoftDeletes
{
    /** The configured `roles.user_model` uses SoftDeletes (evaluated per call: the config is the source of truth). */
    public static function enabled(): bool
    {
        $model = config('roles.user_model');

        return is_string($model) && self::usedBy($model);
    }

    /** @param  class-string|string|Model  $model */
    public static function usedBy(string|Model $model): bool
    {
        $class = $model instanceof Model ? $model::class : $model;

        return class_exists($class) && in_array(SoftDeletes::class, class_uses_recursive($class), true);
    }
}
