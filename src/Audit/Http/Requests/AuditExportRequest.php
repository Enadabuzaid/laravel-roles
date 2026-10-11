<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Http\Requests;

/** The export needs `access.audit.view` and `access.reports.export` (AuditPolicy::export). */
final class AuditExportRequest extends AuditListRequest
{
    protected function ability(): string
    {
        return 'export';
    }
}
