<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Models\Permission;

final class DeprecatedStillHeldCheck implements Check
{
    public function name(): string
    {
        return 'deprecated-still-held';
    }

    public function run(): CheckResult
    {
        $held = Permission::query()
            ->whereNotNull('deprecated_at')
            ->where(fn ($query) => $query->whereHas('roles')->orWhereHas('users'))
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $held === []
            ? CheckResult::pass('No deprecated permission is still granted.')
            : CheckResult::warn('Deprecated but still granted: '.implode(', ', $held));
    }
}
