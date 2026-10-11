<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Support;

use Carbon\CarbonImmutable;
use Enadstack\LaravelRoles\Audit\AuditModules;
use Enadstack\LaravelRoles\Support\EscapedLikeCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Http\Request;

/**
 * Parses and applies the audit filters shared by list, overview, actors and export. Unknown keys and garbage
 * values are ignored, never an error.
 *
 * @phpstan-type Filters array{modules: list<string>, severity: ?string, actor: ?int, actor_system: bool, subject_type: ?string, subject_id: ?int, user: ?int, event: ?string, from: ?CarbonImmutable, to: ?CarbonImmutable, range: ?string, search: ?string}
 */
final class AuditFilters
{
    public const RANGES = ['24h', '7d', '30d', 'all'];

    private const SEVERITIES = ['info', 'warning', 'critical'];

    /** @return Filters */
    public static function from(Request $request): array
    {
        $raw = $request->query('filter');
        $raw = is_array($raw) ? $raw : [];

        $modules = [];
        $requested = $raw['module'] ?? [];
        foreach (is_array($requested) ? $requested : [$requested] as $value) {
            if (is_string($value) && in_array($value, AuditModules::modules(), true)) {
                $modules[$value] = $value;
            }
        }

        $severity = self::string($raw['severity'] ?? null);
        $severity = $severity !== null && in_array($severity, self::SEVERITIES, true) ? $severity : null;

        $actorRaw = self::string($raw['actor'] ?? null);
        $actorSystem = $actorRaw === 'system';
        $actor = $actorRaw !== null && preg_match('/^[0-9]{1,18}$/', $actorRaw) === 1 ? (int) $actorRaw : null;

        $subjectType = null;
        $subjectId = null;
        $subject = self::string($raw['subject'] ?? null);
        if ($subject !== null && preg_match('/^([a-z]{1,20}):([0-9]{1,18})$/', $subject, $match) === 1 && AuditModules::storedTypes($match[1]) !== []) {
            $subjectType = $match[1];
            $subjectId = (int) $match[2];
        }

        $user = self::string($raw['user'] ?? null);
        $user = $user !== null && preg_match('/^[0-9]{1,18}$/', $user) === 1 ? (int) $user : null;

        $event = self::string($raw['event'] ?? null);
        $event = $event !== null && preg_match('/^[a-z0-9_]{1,40}(\.[a-z0-9_]{1,40}){0,3}$/', $event) === 1 ? $event : null;

        $from = self::date($request->query('from'), false);
        $to = self::date($request->query('to'), true);

        $range = self::string($request->query('range'));
        $range = $range !== null && in_array($range, self::RANGES, true) ? $range : '30d';

        if ($from !== null || $to !== null) {
            $range = null;
        }

        $search = self::string($request->query('search'));
        $search = $search !== null ? mb_substr(trim($search), 0, 100) : null;

        return [
            'modules' => array_values($modules),
            'severity' => $severity,
            'actor' => $actor,
            'actor_system' => $actorSystem,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user' => $user,
            'event' => $event,
            'from' => $from,
            'to' => $to,
            'range' => $range,
            'search' => $search === '' ? null : $search,
        ];
    }

    /**
     * Applies the filters to an audit query (Eloquent or base builder).
     *
     * @template TBuilder of Builder|QueryBuilder
     *
     * @param  TBuilder  $query
     * @param  Filters  $filters
     * @return TBuilder
     */
    public static function apply(Builder|QueryBuilder $query, array $filters, bool $withSeverity = true): Builder|QueryBuilder
    {
        if ($filters['modules'] !== []) {
            $query->whereIn('module', $filters['modules']);
        }

        if ($withSeverity && $filters['severity'] !== null) {
            $query->where('severity', $filters['severity']);
        }

        if ($filters['actor_system']) {
            $query->whereNull('actor_id');
        } elseif ($filters['actor'] !== null) {
            $query->where('actor_id', $filters['actor'])->whereIn('actor_type', AuditModules::storedTypes('user'));
        }

        if ($filters['subject_type'] !== null && $filters['subject_id'] !== null) {
            $query->where('subject_id', $filters['subject_id'])->whereIn('subject_type', AuditModules::storedTypes($filters['subject_type']));
        }

        if ($filters['user'] !== null) {
            $id = $filters['user'];
            $types = AuditModules::storedTypes('user');
            $query->where(static function ($q) use ($id, $types): void {
                $q->where(static fn ($s) => $s->where('subject_id', $id)->whereIn('subject_type', $types))
                    ->orWhere(static fn ($a) => $a->where('actor_id', $id)->whereIn('actor_type', $types));
            });
        }

        if ($filters['event'] !== null) {
            $query->where('event', $filters['event']);
        }

        [$from, $to] = self::bounds($filters);

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        if ($filters['search'] !== null) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $base = $query instanceof Builder ? $query->getQuery() : $query;
            $manual = $base->getGrammar() instanceof SQLiteGrammar;

            $query->where(static function ($q) use ($term, $manual): void {
                foreach (['event', 'subject_label', 'actor_label'] as $column) {
                    if ($manual) {
                        $q->orWhere(new EscapedLikeCondition($column))->addBinding($term, 'where');
                    } else {
                        $q->orWhereLike($column, $term);
                    }
                }
            });
        }

        return $query;
    }

    /**
     * The inclusive time window: explicit from/to win over the range.
     *
     * @param  Filters  $filters
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public static function bounds(array $filters): array
    {
        if ($filters['from'] !== null || $filters['to'] !== null) {
            return [$filters['from'], $filters['to']];
        }

        $from = match ($filters['range']) {
            '24h' => CarbonImmutable::now()->subDay(),
            '7d' => CarbonImmutable::now()->subDays(7),
            '30d' => CarbonImmutable::now()->subDays(30),
            default => null,
        };

        return [$from, null];
    }

    /**
     * The normalized filters as a JSON-safe array (set values only), for the export audit entry.
     *
     * @param  Filters  $filters
     * @return array<string, mixed>
     */
    public static function toArray(array $filters): array
    {
        $out = [];

        foreach ($filters as $key => $value) {
            if ($value instanceof CarbonImmutable) {
                $value = $value->toDateString();
            }

            if ($value === null || $value === [] || $value === false) {
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function date(mixed $value, bool $endOfDay): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === null) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
