<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Queries;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\AuditModules;
use Enadstack\LaravelRoles\Audit\Support\AuditFilters;
use Enadstack\LaravelRoles\Support\RawSql;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type Filters from AuditFilters
 */
final class AuditOverviewQuery
{
    /**
     * Counts of the filtered set, minus the severity filter (the stat cards split the current view by severity), in
     * ONE aggregate statement.
     *
     * @param  Filters  $filters
     * @return array{total: int, by_severity: array{info: int, warning: int, critical: int}, by_module: array<string, int>, actors: int}
     */
    public static function summary(array $filters): array
    {
        $grammar = DB::connection()->getQueryGrammar();
        $severity = $grammar->wrap('severity');
        $module = $grammar->wrap('module');
        $actor = $grammar->wrap('actor_id');

        $columns = [
            'count(*) as total',
            "sum(case when {$severity} = 'info' then 1 else 0 end) as sev_info",
            "sum(case when {$severity} = 'warning' then 1 else 0 end) as sev_warning",
            "sum(case when {$severity} = 'critical' then 1 else 0 end) as sev_critical",
            "count(distinct {$actor}) as actor_ids",
            "max(case when {$actor} is null then 1 else 0 end) as has_system",
        ];
        $bindings = [];
        $modules = AuditModules::modules();

        foreach ($modules as $index => $name) {
            $columns[] = "sum(case when {$module} = ? then 1 else 0 end) as mod_{$index}";
            $bindings[] = $name;
        }

        $query = ActionLog::query()->toBase();
        AuditFilters::apply($query, $filters, withSeverity: false);
        $row = (array) $query->select(new RawSql(implode(', ', $columns)))->addBinding($bindings, 'select')->first();

        $byModule = [];
        foreach ($modules as $index => $name) {
            $byModule[$name] = (int) ($row["mod_{$index}"] ?? 0);
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'by_severity' => [
                'info' => (int) ($row['sev_info'] ?? 0),
                'warning' => (int) ($row['sev_warning'] ?? 0),
                'critical' => (int) ($row['sev_critical'] ?? 0),
            ],
            'by_module' => $byModule,
            'actors' => (int) ($row['actor_ids'] ?? 0) + (int) ($row['has_system'] ?? 0),
        ];
    }
}
