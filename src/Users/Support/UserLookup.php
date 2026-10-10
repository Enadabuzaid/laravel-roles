<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Support;

use Enadstack\LaravelRoles\Support\RawSql;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/** Email handling shared by the invitation Actions. */
final class UserLookup
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Whether any user of the configured model already has this email (case-insensitive).
     *
     * @param  bool  $includeTrashed  also look at soft-deleted users (an address in the trash is still taken)
     * @param  int|string|null  $ignoring  a user key that does not count (the user being edited or restored)
     */
    public static function emailTaken(string $email, bool $includeTrashed = false, int|string|null $ignoring = null): bool
    {
        $model = self::model();
        $query = $model->newQuery();

        if ($includeTrashed && HostSoftDeletes::usedBy($model)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        if ($ignoring !== null) {
            $query->whereKeyNot($ignoring);
        }

        $column = $query->getQuery()->getGrammar()->wrap($model->qualifyColumn('email'));

        return $query->whereRaw(new RawSql("lower({$column}) = ?"), [self::normalize($email)])->exists();
    }

    public static function model(): Model
    {
        $class = config('roles.user_model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new \LogicException('roles.user_model must be an Eloquent model class.');
        }

        return new $class;
    }
}
