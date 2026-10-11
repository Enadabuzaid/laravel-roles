<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Queries;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\AuditModules;
use Enadstack\LaravelRoles\Audit\Support\AuditFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * @phpstan-import-type Filters from AuditFilters
 */
final class AuditLogQuery
{
    /** Columns of a list row; the value columns feed the changes count and the summary params. */
    public const COLUMNS = [
        'id', 'event', 'module', 'severity', 'created_at', 'ip_address', 'request_id',
        'actor_type', 'actor_id', 'actor_label', 'subject_type', 'subject_id', 'subject_label',
        'old_values', 'new_values', 'meta',
    ];

    /**
     * Newest first. Each row carries `actor_exists` and `subject_exists`, resolved with one id query per
     * distinct stored type on the page (never per row).
     *
     * @param  Filters  $filters
     * @return LengthAwarePaginator<int, ActionLog>
     */
    public static function paginate(array $filters, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $query = ActionLog::query()->select(self::COLUMNS);
        AuditFilters::apply($query, $filters);
        $query->orderByDesc('created_at')->orderByDesc('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        self::attachExistence($paginator->getCollection());

        return $paginator;
    }

    /**
     * Row count and highest id of the filtered set in one query.
     *
     * @param  Filters  $filters
     * @return array{count: int, max_id: int}
     */
    public static function totals(array $filters): array
    {
        $row = AuditFilters::apply(ActionLog::query()->toBase(), $filters)
            ->selectRaw('count(*) as aggregate_count, max(id) as aggregate_max')
            ->first();

        return ['count' => (int) ($row->aggregate_count ?? 0), 'max_id' => (int) ($row->aggregate_max ?? 0)];
    }

    /**
     * The filtered rows for the CSV, newest first in constant memory, never past `$maxId`.
     *
     * @param  Filters  $filters
     * @return LazyCollection<int, ActionLog>
     */
    public static function stream(array $filters, int $maxId): LazyCollection
    {
        $query = ActionLog::query()->select(self::COLUMNS)->where('id', '<=', $maxId);
        AuditFilters::apply($query, $filters);

        return $query->lazyByIdDesc(500);
    }

    /**
     * Sets `actor_exists` and `subject_exists` on every row, one id query per distinct stored type.
     *
     * @param  Collection<int, ActionLog>  $rows
     */
    public static function attachExistence(Collection $rows): void
    {
        /** @var array<string, list<int|string>> $wanted */
        $wanted = [];

        foreach ($rows as $row) {
            if ($row->actor_type !== null && $row->actor_id !== null) {
                $wanted[$row->actor_type][] = $row->actor_id;
            }
            if ($row->subject_type !== null && $row->subject_id !== null) {
                $wanted[$row->subject_type][] = $row->subject_id;
            }
        }

        /** @var array<string, array<int|string, true>> $found */
        $found = [];

        foreach ($wanted as $type => $ids) {
            $class = AuditModules::modelClass($type);

            if ($class === null) {
                continue;
            }

            /** @var Builder<Model> $builder */
            $builder = $class::query()->withoutGlobalScopes();
            $key = (new $class)->getKeyName();

            foreach ($builder->whereIn($key, array_values(array_unique($ids)))->pluck($key) as $id) {
                $found[$type][$id] = true;
            }
        }

        foreach ($rows as $row) {
            $row->setAttribute('actor_exists', $row->actor_type === null || isset($found[$row->actor_type][$row->actor_id]));
            $row->setAttribute('subject_exists', $row->subject_type !== null && isset($found[$row->subject_type][$row->subject_id]));
        }
    }
}
