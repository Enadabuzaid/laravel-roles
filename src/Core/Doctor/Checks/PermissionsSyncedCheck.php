<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Doctor\Checks;

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistryException;
use Enadstack\LaravelRoles\Models\Permission;

final class PermissionsSyncedCheck implements Check
{
    public function __construct(private readonly PermissionRegistry $registry) {}

    public function name(): string
    {
        return 'permissions-synced';
    }

    public function run(): CheckResult
    {
        try {
            $names = array_map(fn ($definition): string => $definition->name, $this->registry->definitions());
        } catch (PermissionRegistryException $e) {
            return CheckResult::fail($e->getMessage());
        }

        $active = Permission::query()
            ->where('guard_name', (string) config('roles.guard'))
            ->whereNull('deprecated_at')
            ->pluck('name')
            ->all();
        $missing = array_values(array_diff($names, $active));

        return $missing === []
            ? CheckResult::pass(count($names).' registry permission(s) present.')
            : CheckResult::fail('Missing or deprecated: '.implode(', ', $missing).'. Run roles:sync.');
    }
}
