<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The optional host column that also records MFA (`roles.users.mfa_enabled_column`, a nullable timestamp such as
 * Fortify's `two_factor_confirmed_at`): resolved only when the column exists on the user table, and checked
 * against the schema at most once per table and column name for the lifetime of this (scoped) instance.
 */
final class HostMfaColumn
{
    /** @var array<string, string|null> */
    private array $resolved = [];

    public function column(): ?string
    {
        $configured = config('roles.users.mfa_enabled_column');

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        $model = config('roles.user_model');
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        $table = (new $model)->getTable();

        $key = $table.'.'.$configured;

        if (! array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = Schema::hasColumn($table, $configured) ? $configured : null;
        }

        return $this->resolved[$key];
    }
}
