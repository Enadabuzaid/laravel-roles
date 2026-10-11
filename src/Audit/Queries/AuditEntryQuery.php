<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit\Queries;

use Enadstack\ActionLogger\Models\ActionLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class AuditEntryQuery
{
    /**
     * One entry with its existence flags (a constant, small number of queries).
     *
     * @throws ModelNotFoundException
     */
    public static function find(int|string $id): ActionLog
    {
        $log = ActionLog::query()->select([...AuditLogQuery::COLUMNS, 'user_agent'])->findOrFail($id);

        AuditLogQuery::attachExistence(new Collection([$log]));

        return $log;
    }
}
