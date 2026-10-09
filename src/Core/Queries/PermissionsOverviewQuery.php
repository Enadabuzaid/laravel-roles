<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Queries;

use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

final class PermissionsOverviewQuery
{
    /**
     * Grants and role counts only consider non-trashed roles. Five constant queries.
     *
     * @return array{total: int, active: int, deprecated: int, high_risk: int, manual: int, modules_count: int, unused: int, total_grants: int, avg_per_role: float, by_module: array<int,array{module: string, count: int, high_risk: int}>, owner_only: array<int,array{id: mixed, name: string, label_text: string, module: string|null, risk: string}>}
     */
    public static function summary(): array
    {
        $tables = (array) config('permission.table_names');
        $permissions = (string) ($tables['permissions'] ?? 'permissions');
        $roles = (string) ($tables['roles'] ?? 'roles');
        $pivot = (string) ($tables['role_has_permissions'] ?? 'role_has_permissions');
        $guard = (string) config('roles.guard');

        $heldByLiveRole = "exists (select 1 from {$pivot} p join {$roles} r on r.id = p.role_id where p.permission_id = {$permissions}.id and r.deleted_at is null)";

        $columns = 'count(*) as total, '
            .'sum(case when deprecated_at is null then 1 else 0 end) as active, '
            .'sum(case when deprecated_at is not null then 1 else 0 end) as deprecated, '
            ."sum(case when deprecated_at is null and risk = 'high' then 1 else 0 end) as high_risk, "
            ."sum(case when source = 'manual' then 1 else 0 end) as manual, "
            .'count(distinct case when deprecated_at is null then module end) as modules_count, '
            ."sum(case when deprecated_at is null and not {$heldByLiveRole} then 1 else 0 end) as unused";

        /** @phpstan-ignore argument.type */
        $counts = DB::table($permissions)->where('guard_name', $guard)->select(new Expression($columns))->first();

        $grants = (int) DB::table($pivot)
            ->join($roles, $roles.'.id', '=', $pivot.'.role_id')
            ->whereNull($roles.'.deleted_at')
            ->count();
        $liveRoles = (int) DB::table($roles)->where('guard_name', $guard)->whereNull('deleted_at')->count();

        $byModule = DB::table($permissions)
            ->where('guard_name', $guard)->whereNull('deprecated_at')->whereNotNull('module')
            ->selectRaw("module, count(*) as total, sum(case when risk = 'high' then 1 else 0 end) as high_risk")
            ->groupBy('module')->orderBy('module')->get()
            ->map(static fn (object $row): array => ['module' => (string) $row->module, 'count' => (int) $row->total, 'high_risk' => (int) $row->high_risk])
            ->values()->all();

        $super = config('roles.super_role');
        $heldByOthers = DB::table($pivot)
            ->join($roles, $roles.'.id', '=', $pivot.'.role_id')
            ->whereNull($roles.'.deleted_at')
            ->when(is_string($super) && $super !== '', static fn ($query) => $query->where($roles.'.name', '!=', $super))
            ->whereColumn($pivot.'.permission_id', $permissions.'.id')
            ->select(new Expression('1'));

        $ownerOnly = Permission::query()
            ->where('guard_name', $guard)->whereNull('deprecated_at')
            ->whereNotExists($heldByOthers)
            ->orderBy('sort_order')->orderBy('name')->limit(5)
            ->get()
            ->map(static fn (Permission $permission): array => [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'label_text' => $permission->label_text,
                'module' => $permission->module,
                'risk' => $permission->risk,
            ])->values()->all();

        return [
            'total' => (int) ($counts->total ?? 0),
            'active' => (int) ($counts->active ?? 0),
            'deprecated' => (int) ($counts->deprecated ?? 0),
            'high_risk' => (int) ($counts->high_risk ?? 0),
            'manual' => (int) ($counts->manual ?? 0),
            'modules_count' => (int) ($counts->modules_count ?? 0),
            'unused' => (int) ($counts->unused ?? 0),
            'total_grants' => $grants,
            'avg_per_role' => $liveRoles > 0 ? round($grants / $liveRoles, 2) : 0.0,
            'by_module' => $byModule,
            'owner_only' => $ownerOnly,
        ];
    }
}
