<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Queries;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\Support\AuditFilters;

/**
 * @phpstan-import-type Filters from AuditFilters
 */
final class AuditActorsQuery
{
    /**
     * Distinct actors of the filtered set (minus the severity filter), most active first; the system actor is
     * `{id: null, type: 'system'}`. The label is the newest-known snapshot (`#<id>` for entries written before labels existed).
     *
     * @param  Filters  $filters
     * @return list<array{id: int|null, type: 'user'|'system', label: string, count: int}>
     */
    public static function list(array $filters, int $limit = 50): array
    {
        $query = ActionLog::query()->toBase();
        AuditFilters::apply($query, $filters, withSeverity: false);

        $rows = $query
            ->selectRaw('actor_id, max(actor_label) as label, count(*) as aggregate_count')
            ->groupBy('actor_id')
            ->orderByDesc('aggregate_count')
            ->orderByRaw('case when actor_id is null then 1 else 0 end')
            ->orderBy('actor_id')
            ->limit(max(1, $limit))
            ->get();

        $actors = [];

        foreach ($rows as $row) {
            $id = $row->actor_id === null ? null : (int) $row->actor_id;
            $label = is_string($row->label) && $row->label !== '' ? $row->label : ($id === null ? (string) trans('access::audit.system') : '#'.$id);

            $actors[] = ['id' => $id, 'type' => $id === null ? 'system' : 'user', 'label' => $label, 'count' => (int) $row->aggregate_count];
        }

        return $actors;
    }
}
