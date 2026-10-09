<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Queries;

use Enadstack\LaravelRoles\Support\ListDefinition;
use Enadstack\LaravelRoles\Support\ListQuery;
use Enadstack\LaravelRoles\Support\RawSql;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\UserAccessState;
use Enadstack\LaravelRoles\Users\Support\HostMfaColumn;
use Enadstack\LaravelRoles\Users\Support\TargetStanding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Users of the configured model LEFT JOINed to their access state; users without a state row count as the
 * configured default status (`active`). The state columns come back as `access_*` aliases so no row needs a
 * second query.
 */
final class UsersQuery
{
    public static function definition(): ListDefinition
    {
        $users = self::userTable();

        return new ListDefinition(
            filters: [
                'status' => static function (Builder $query, mixed $value): void {
                    $statuses = array_values(array_filter(
                        array_map('strval', (array) $value),
                        static fn (string $status): bool => UserStatus::tryFrom($status) !== null,
                    ));

                    if ($statuses !== []) {
                        $query->whereRaw(new RawSql(self::statusExpression().' in ('.implode(', ', array_fill(0, count($statuses), '?')).')'), [self::defaultStatus(), ...$statuses]);
                    }
                },
                'role' => static function (Builder $query, mixed $value): void {
                    self::filterRole($query, array_values((array) $value));
                },
                'mfa' => static function (Builder $query, mixed $value): void {
                    self::filterMfa($query, $value);
                },
                'last_login_from' => static fn (Builder $query, mixed $value) => self::filterDate($query, self::stateColumn('last_login_at'), $value, '>=', false),
                'last_login_to' => static fn (Builder $query, mixed $value) => self::filterDate($query, self::stateColumn('last_login_at'), $value, '<=', true),
                'joined_from' => static fn (Builder $query, mixed $value) => self::filterDate($query, $users.'.created_at', $value, '>=', false),
                'joined_to' => static fn (Builder $query, mixed $value) => self::filterDate($query, $users.'.created_at', $value, '<=', true),
                'no_role' => static function (Builder $query, mixed $value): void {
                    if ($value === 'yes') {
                        $query->whereNotIn($query->getModel()->getQualifiedKeyName(), self::roleHolders());
                    }
                },
            ],
            sorts: [
                'name' => $users.'.name',
                'email' => $users.'.email',
                'status' => 'access_status',
                'last_login' => 'access_last_login_at',
                'created' => $users.'.created_at',
            ],
            includes: ['roles'],
            searchable: ['name', 'email'],
            defaultSort: 'name',
        );
    }

    /**
     * The page of users with each row's lock standing relative to `$actor` already attached.
     *
     * @return LengthAwarePaginator<int, Model>
     */
    public static function paginate(ListQuery $query, ?Model $actor): LengthAwarePaginator
    {
        $builder = self::base();
        $query->apply($builder);

        if (in_array('roles', $query->includes, true)) {
            $builder->with(['roles' => static function ($relation): void {
                $relation->where('roles.guard_name', (string) config('roles.guard'))
                    ->select(['roles.id', 'roles.name', 'roles.label', 'roles.color', 'roles.is_system'])
                    ->orderBy('roles.name');
            }]);
        }

        $page = $query->paginate($builder);

        if ($actor !== null) {
            self::annotate($actor, $page->getCollection());
        }

        return $page;
    }

    /**
     * Joined users query: `users.*` plus the `access_*` state aliases.
     *
     * @return Builder<Model>
     */
    public static function base(): Builder
    {
        $states = self::stateTable();

        return self::joined()
            ->select(self::userTable().'.*')
            ->addSelect(new RawSql(self::statusExpression().' as access_status'))
            ->addBinding(self::defaultStatus(), 'select')
            ->addSelect([
                $states.'.status_reason as access_status_reason',
                $states.'.last_login_at as access_last_login_at',
                $states.'.last_login_ip as access_last_login_ip',
                $states.'.mfa_enabled_at as access_mfa_enabled_at',
            ]);
    }

    /**
     * Users LEFT JOINed to their access state, without any selected columns.
     *
     * @return Builder<Model>
     */
    public static function joined(): Builder
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');
        $states = self::stateTable();

        /** @var Builder<Model> $builder */
        $builder = $userModel::query()->leftJoin($states, $states.'.user_id', '=', (new $userModel)->getQualifiedKeyName());

        return $builder;
    }

    /**
     * Attaches `access_standing` (see TargetStanding) to every user, with a fixed number of queries.
     *
     * @param  iterable<Model>  $users
     */
    public static function annotate(Model $actor, iterable $users): void
    {
        $standings = app(TargetStanding::class)->evaluate($actor, $users);

        foreach ($users as $user) {
            $user->setAttribute('access_standing', $standings[$user->getKey()]);
        }
    }

    public static function defaultStatus(): string
    {
        return UserStatus::Active->value;
    }

    public static function stateTable(): string
    {
        return (new UserAccessState)->getTable();
    }

    public static function statusExpression(): string
    {
        return 'COALESCE('.self::grammar()->wrap(self::stateTable().'.status').', ?)';
    }

    public static function userTable(): string
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');

        return (new $userModel)->getTable();
    }

    /** Users holding at least one live role of the configured guard (a subquery of user keys). */
    public static function roleHolders(): QueryBuilder
    {
        $tables = (array) config('permission.table_names');
        $roles = (string) ($tables['roles'] ?? 'roles');
        $pivot = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
        $morphKey = (string) config('permission.column_names.model_morph_key', 'model_id');

        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');

        return DB::table($pivot)
            ->join($roles, $roles.'.id', '=', $pivot.'.role_id')
            ->where($pivot.'.model_type', (new $userModel)->getMorphClass())
            ->where($roles.'.guard_name', (string) config('roles.guard'))
            ->whereNull($roles.'.deleted_at')
            ->select($pivot.'.'.$morphKey);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<mixed>  $references  role ids or names
     */
    private static function filterRole(Builder $query, array $references): void
    {
        $ids = [];
        $names = [];

        foreach ($references as $reference) {
            if (! is_scalar($reference)) {
                continue;
            }

            $reference = (string) $reference;
            $names[] = $reference;

            if (preg_match('/^[0-9]{1,18}$/', $reference) === 1) {
                $ids[] = (int) $reference;
            }
        }

        if ($names === []) {
            return;
        }

        $query->whereIn($query->getModel()->getQualifiedKeyName(), self::roleHolders()->where(static function (QueryBuilder $match) use ($ids, $names): void {
            $roles = (string) ((array) config('permission.table_names'))['roles'];
            $match->whereIn($roles.'.name', $names);

            if ($ids !== []) {
                $match->orWhereIn($roles.'.id', $ids);
            }
        }));
    }

    /** @param  Builder<Model>  $query */
    private static function filterMfa(Builder $query, mixed $value): void
    {
        if ($value !== 'on' && $value !== 'off') {
            return;
        }

        $column = app(HostMfaColumn::class)->column();
        $state = self::stateColumn('mfa_enabled_at');

        $query->where(static function (Builder $group) use ($value, $state, $column): void {
            $host = $column !== null ? self::userTable().'.'.$column : null;

            if ($value === 'on') {
                $group->whereNotNull($state);
                if ($host !== null) {
                    $group->orWhereNotNull($host);
                }

                return;
            }

            $group->whereNull($state);
            if ($host !== null) {
                $group->whereNull($host);
            }
        });
    }

    /** @param  Builder<Model>  $query */
    private static function filterDate(Builder $query, string $column, mixed $value, string $operator, bool $endOfDay): void
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) !== 1 || ! checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return;
        }

        $day = Carbon::createFromFormat('!Y-m-d', $value);

        if ($day === null) {
            return;
        }

        $query->where($column, $operator, $endOfDay ? $day->endOfDay() : $day->startOfDay());
    }

    private static function stateColumn(string $column): string
    {
        return self::stateTable().'.'.$column;
    }

    private static function grammar(): Grammar
    {
        return DB::connection()->getQueryGrammar();
    }
}
