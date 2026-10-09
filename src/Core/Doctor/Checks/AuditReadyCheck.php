<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Illuminate\Support\Facades\Schema;

final class AuditReadyCheck implements Check
{
    private const REQUIRED_COLUMNS = ['module', 'severity', 'request_id'];

    public function name(): string
    {
        return 'audit-ready';
    }

    public function run(): CheckResult
    {
        if (! config('roles.modules.audit')) {
            return CheckResult::pass('Audit module disabled.');
        }

        if (! Schema::hasTable('action_logs')) {
            return CheckResult::fail('The action_logs table is missing — run php artisan migrate.');
        }

        $missing = array_values(array_filter(
            self::REQUIRED_COLUMNS,
            static fn (string $column): bool => ! Schema::hasColumn('action_logs', $column),
        ));

        if ($missing !== []) {
            return CheckResult::fail('action_logs is missing columns: '.implode(', ', $missing).' — run php artisan migrate.');
        }

        if (! config('action-logger.immutable')) {
            return CheckResult::warn('Audit entries are not immutable — set ACTION_LOGGER_IMMUTABLE=true.');
        }

        return CheckResult::pass('Audit log is ready and immutable.');
    }
}
