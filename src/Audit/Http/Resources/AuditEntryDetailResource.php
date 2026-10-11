<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Http\Resources;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Audit\AuditModules;

/** The list fields plus the field-level diff, the redacted meta and the user agent. */
final class AuditEntryDetailResource extends AuditEntryResource
{
    protected function extra(ActionLog $log, array $changes): array
    {
        $meta = is_array($log->meta) ? AuditModules::redactor()->redact($log->meta) : null;

        return [
            'changes' => $changes,
            'meta' => $meta === [] ? null : $meta,
            'user_agent' => $log->user_agent,
        ];
    }
}
