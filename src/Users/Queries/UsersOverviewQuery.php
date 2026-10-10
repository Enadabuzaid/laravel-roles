<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Queries;

use Enadstack\LaravelRoles\Support\RawSql;
use Enadstack\LaravelRoles\Users\Enums\UserStatus;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Enadstack\LaravelRoles\Users\Support\HostMfaColumn;
use Enadstack\LaravelRoles\Users\Support\HostSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UsersOverviewQuery
{
    private const WEEKS = 8;

    /**
     * Counts over the configured user model in one SQL statement (conditional aggregates, portable to
     * sqlite, pgsql and mysql) plus two small counts. `series` has `WEEKS` rolling weekly buckets ending now:
     * `joined[i]` counts users created in (b[i], b[i+1]] and `members[i]` those created up to b[i+1].
     *
     * @return array{total_members: int, active: int, pending_invites: int, suspended: int, locked: int, deactivated: int, without_mfa: int, without_role: int, joined_last_30_days: int, trashed: int, series: array{members: list<int>, joined: list<int>}}
     */
    public static function summary(): array
    {
        $users = UsersQuery::userTable();
        $grammar = DB::connection()->getQueryGrammar();
        $created = $grammar->wrap($users.'.created_at');
        $status = UsersQuery::statusExpression();
        $default = UsersQuery::defaultStatus();

        $end = Carbon::now();
        $boundaries = [];
        for ($i = 0; $i <= self::WEEKS; $i++) {
            $boundaries[] = $end->copy()->subWeeks(self::WEEKS - $i)->format('Y-m-d H:i:s');
        }

        $columns = ['count(*) as total'];
        $bindings = [];

        foreach ([UserStatus::Active, UserStatus::Suspended, UserStatus::Locked, UserStatus::Deactivated] as $case) {
            $columns[] = "sum(case when {$status} = ? then 1 else 0 end) as status_{$case->value}";
            array_push($bindings, $default, $case->value);
        }

        $noMfa = $grammar->wrap(UsersQuery::stateTable().'.mfa_enabled_at').' is null';
        $hostColumn = app(HostMfaColumn::class)->column();
        if ($hostColumn !== null) {
            $noMfa .= ' and '.$grammar->wrap($users.'.'.$hostColumn).' is null';
        }
        $columns[] = "sum(case when {$noMfa} then 1 else 0 end) as without_mfa";

        $columns[] = "sum(case when {$created} >= ? then 1 else 0 end) as joined_last_30_days";
        $bindings[] = $end->copy()->subDays(30)->format('Y-m-d H:i:s');

        for ($i = 0; $i < self::WEEKS; $i++) {
            $columns[] = "sum(case when {$created} > ? and {$created} <= ? then 1 else 0 end) as joined_{$i}";
            array_push($bindings, $boundaries[$i], $boundaries[$i + 1]);
            $columns[] = "sum(case when {$created} <= ? then 1 else 0 end) as members_{$i}";
            $bindings[] = $boundaries[$i + 1];
        }

        $row = UsersQuery::joined()->toBase()->select(new RawSql(implode(', ', $columns)))->addBinding($bindings, 'select')->first();
        $counts = (array) $row;

        $joined = [];
        $members = [];
        for ($i = 0; $i < self::WEEKS; $i++) {
            $joined[] = (int) ($counts["joined_{$i}"] ?? 0);
            $members[] = (int) ($counts["members_{$i}"] ?? 0);
        }

        return [
            'total_members' => (int) ($counts['total'] ?? 0),
            'active' => (int) ($counts['status_active'] ?? 0),
            'pending_invites' => AccessInvitation::query()->pending()->count(),
            'suspended' => (int) ($counts['status_suspended'] ?? 0),
            'locked' => (int) ($counts['status_locked'] ?? 0),
            'deactivated' => (int) ($counts['status_deactivated'] ?? 0),
            'without_mfa' => (int) ($counts['without_mfa'] ?? 0),
            'without_role' => self::withoutRole(),
            'trashed' => self::trashed(),
            'joined_last_30_days' => (int) ($counts['joined_last_30_days'] ?? 0),
            'series' => ['members' => $members, 'joined' => $joined],
        ];
    }

    /** Users in the trash (0 for hosts without SoftDeletes). */
    private static function trashed(): int
    {
        if (! HostSoftDeletes::enabled()) {
            return 0;
        }

        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');

        return $userModel::query()->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull((new $userModel)->qualifyColumn('deleted_at'))->count();
    }

    private static function withoutRole(): int
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('roles.user_model');
        $user = new $userModel;

        return $userModel::query()->whereNotIn($user->getQualifiedKeyName(), UsersQuery::roleHolders())->count();
    }
}
