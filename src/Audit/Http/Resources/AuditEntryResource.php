<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Http\Resources;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\AuditModules;
use Enadstack\LaravelRoles\Audit\Support\AuditSummary;
use Enadstack\LaravelRoles\Audit\Support\ChangeSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One list row of the audit log. Values are redacted before they are diffed or counted. */
class AuditEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $log = $this->log();
        $redactor = AuditModules::redactor();
        $old = is_array($log->old_values) ? $log->old_values : null;
        $new = is_array($log->new_values) ? $log->new_values : null;
        $changes = ChangeSet::between($old, $new, $redactor);

        return [
            'id' => $log->getKey(),
            'event' => $log->event,
            'module' => $log->module,
            'severity' => $log->severity,
            'created_at' => $log->created_at->toIso8601String(),
            'ip_address' => $log->ip_address,
            'request_id' => $log->request_id,
            'actor' => $this->actor($log),
            'subject' => $this->subject($log),
            'summary' => AuditSummary::for($log),
            'changes_count' => count($changes),
            ...$this->extra($log, $changes),
        ];
    }

    /**
     * @param  list<array{field: string, from: mixed, to: mixed, kind: string}>  $changes
     * @return array<string, mixed>
     */
    protected function extra(ActionLog $log, array $changes): array
    {
        return [];
    }

    protected function log(): ActionLog
    {
        $log = $this->resource;

        if (! $log instanceof ActionLog) {
            throw new \LogicException('AuditEntryResource wraps an ActionLog.');
        }

        return $log;
    }

    /** @return array{type: string, id: int|null, label: string|null, exists: bool} */
    private function actor(ActionLog $log): array
    {
        if ($log->actor_type === null) {
            return ['type' => 'system', 'id' => null, 'label' => null, 'exists' => true];
        }

        return [
            'type' => AuditModules::typeName($log->actor_type) ?? 'other',
            'id' => $log->actor_id,
            'label' => $log->actor_label,
            'exists' => (bool) $log->getAttribute('actor_exists'),
        ];
    }

    /** @return array{type: string|null, id: int|null, label: string|null, exists: bool} */
    private function subject(ActionLog $log): array
    {
        return [
            'type' => AuditModules::typeName($log->subject_type),
            'id' => $log->subject_id,
            'label' => $log->subject_label,
            'exists' => (bool) $log->getAttribute('subject_exists'),
        ];
    }
}
