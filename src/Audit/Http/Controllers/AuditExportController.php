<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Http\Controllers;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\AuditModules;
use Enadstack\LaravelRoles\Audit\AuditRecorder;
use Enadstack\LaravelRoles\Audit\Http\Requests\AuditExportRequest;
use Enadstack\LaravelRoles\Audit\Queries\AuditLogQuery;
use Enadstack\LaravelRoles\Audit\Support\AuditFilters;
use Enadstack\LaravelRoles\Audit\Support\AuditSummary;
use Enadstack\LaravelRoles\Audit\Support\ChangeSet;
use Enadstack\LaravelRoles\Support\Csv\CsvStream;
use Enadstack\LaravelRoles\Support\Exceptions\AccessException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditExportController
{
    private const HEADERS = ['time', 'actor', 'module', 'event', 'summary', 'target', 'severity', 'ip', 'changes'];

    public function __invoke(AuditExportRequest $request, AuditRecorder $audit): StreamedResponse
    {
        $filters = AuditFilters::from($request);
        $totals = AuditLogQuery::totals($filters);
        $max = max(1, (int) config('roles.audit.export_max_rows', 50000));

        if ($totals['count'] > $max) {
            throw new AccessException(
                (string) trans('access::audit.export_too_large', ['count' => $totals['count'], 'max' => $max]),
                'export.too_large',
                422,
                ['count' => $totals['count'], 'max' => $max],
            );
        }

        $audit->record('audit', 'exported', null, null, null, [
            'filters' => AuditFilters::toArray($filters),
            'rows' => $totals['count'],
        ]);

        $module = count($filters['modules']) === 1 ? $filters['modules'][0] : 'all';
        $filename = 'audit-'.$module.'-'.now()->format('Ymd-Hi').'.csv';
        $headers = array_map(static fn (string $key): string => (string) trans('access::audit.csv.'.$key), self::HEADERS);

        $rows = (static function () use ($filters, $totals): \Generator {
            foreach (AuditLogQuery::stream($filters, $totals['max_id']) as $log) {
                yield self::row($log);
            }
        })();

        return CsvStream::download($filename, $headers, $rows);
    }

    /** @return list<string|null> */
    private static function row(ActionLog $log): array
    {
        $summary = AuditSummary::for($log);
        $redactor = AuditModules::redactor();
        $changes = array_map(
            static fn (array $change): string => $change['field'].': '.self::value($change['from']).' → '.self::value($change['to']),
            ChangeSet::between(
                is_array($log->old_values) ? $log->old_values : null,
                is_array($log->new_values) ? $log->new_values : null,
                $redactor,
            ),
        );

        $key = str_replace('access.audit.', 'access::audit.', $summary['key']);

        return [
            $log->created_at->utc()->toIso8601String(),
            $summary['params']['actor'],
            $log->module,
            $log->event,
            (string) trans($key, $summary['params']),
            $log->subject_label ?? ($log->subject_id !== null ? '#'.$log->subject_id : ''),
            $log->severity,
            $log->ip_address,
            implode('; ', $changes),
        ];
    }

    private static function value(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE), $value));
        }

        return (string) $value;
    }
}
