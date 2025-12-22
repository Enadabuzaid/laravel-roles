<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Commands;

use Illuminate\Console\Command;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Contracts\RolePermissionSyncServiceContract;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * SyncCommand
 *
 * Idempotent command that syncs roles and permissions from config.
 * Supports wildcard expansion, tenancy modes, and guard enforcement.
 *
 * @package Enadstack\LaravelRoles\Commands
 */
class SyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'roles:sync
        {--guard= : Override guard (default from config)}
        {--team-id= : When team_scoped, run sync against a specific tenant/team id}
        {--no-map : Seed roles/permissions but skip mapping}
        {--prune : Remove DB permissions not present in config}
        {--dry-run : Show what would change without writing}
        {--verbose-output : Show detailed output}
    ';

    /**
     * @var string
     */
    protected $description = 'Sync roles & permissions from config (idempotent).';

    /**
     * Tenant context instance.
     *
     * @var TenantContextContract
     */
    protected TenantContextContract $tenantContext;

    /**
     * Guard resolver instance.
     *
     * @var GuardResolverContract
     */
    protected GuardResolverContract $guardResolver;

    /**
     * Cache key builder instance.
     *
     * @var CacheKeyBuilderContract
     */
    protected CacheKeyBuilderContract $cacheKeyBuilder;

    /**
     * Sync service instance.
     *
     * @var RolePermissionSyncServiceContract
     */
    protected RolePermissionSyncServiceContract $syncService;

    /**
     * Create a new command instance.
     *
     * @param TenantContextContract $tenantContext
     * @param GuardResolverContract $guardResolver
     * @param CacheKeyBuilderContract $cacheKeyBuilder
     * @param RolePermissionSyncServiceContract $syncService
     */
    public function __construct(
        TenantContextContract $tenantContext,
        GuardResolverContract $guardResolver,
        CacheKeyBuilderContract $cacheKeyBuilder,
        RolePermissionSyncServiceContract $syncService
    ) {
        parent::__construct();

        $this->tenantContext = $tenantContext;
        $this->guardResolver = $guardResolver;
        $this->cacheKeyBuilder = $cacheKeyBuilder;
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $guard = $this->option('guard') ?: config('roles.guard', 'web');
        $dry = (bool) $this->option('dry-run');
        $verboseOutput = (bool) $this->option('verbose-output');

        $this->info('🔄 Laravel Roles Sync');
        $this->line('');

        // Display context
        $this->displayContext($guard);

        // Apply team/tenancy context
        $this->applyTenancyContext();

        // Step 1: Sync roles
        $this->syncRoles($guard, $dry, $verboseOutput);

        // Step 2: Sync permissions
        $this->syncPermissions($guard, $dry, $verboseOutput);

        // Step 3: Apply mapping (unless --no-map)
        if (!$this->option('no-map')) {
            $this->applyMapping($guard, $dry, $verboseOutput);
        } else {
            $this->components->info('Skipped mapping (--no-map flag set).');
        }

        // Step 4: Prune (if --prune flag)
        if ($this->option('prune')) {
            $this->prune($guard, $dry);
        }

        // Step 5: Update labels/descriptions
        $this->updateLabelsAndDescriptions($guard, $dry, $verboseOutput);

        // Reset cache
        if (!$dry) {
            $this->cacheKeyBuilder->flushAll();
            $this->callSilent('permission:cache-reset');
        }

        $this->line('');
        $this->info($dry ? '✅ Dry-run complete.' : '✅ Sync complete.');

        return self::SUCCESS;
    }

    /**
     * Display current context.
     *
     * @param string $guard
     * @return void
     */
    protected function displayContext(string $guard): void
    {
        $mode = $this->tenantContext->mode();
        $tenantId = $this->tenantContext->tenantId();

        $this->components->twoColumnDetail('Tenancy Mode', $mode);
        $this->components->twoColumnDetail('Guard', $guard);
        $this->components->twoColumnDetail('Tenant ID', $tenantId ?? 'N/A');
        $this->components->twoColumnDetail('Scope Key', $this->tenantContext->scopeKey());
        $this->line('');
    }

    /**
     * Apply tenancy context.
     *
     * @return void
     */
    protected function applyTenancyContext(): void
    {
        $teamId = $this->option('team-id');

        if ($this->tenantContext->isTeamScoped() && $teamId !== null) {
            $this->tenantContext->setTenantId($teamId);
            $this->tenantContext->applyToSpatie();
            $this->components->info("Syncing under team_id={$teamId}");
        }
    }

    /**
     * Sync roles from config.
     *
     * @param string $guard
     * @param bool $dry
     * @param bool $verbose
     * @return void
     */
    protected function syncRoles(string $guard, bool $dry, bool $verbose): void
    {
        $this->components->task('Syncing roles', function () use ($guard, $dry, $verbose) {
            if ($dry) {
                return 'dry-run';
            }

            $roles = (array) config('roles.seed.roles', []);
            $defaultRoles = ['super-admin', 'admin', 'user'];
            $allRoles = array_unique(array_merge($defaultRoles, $roles));

            $roleDescriptions = (array) config('roles.seed.role_descriptions', []);

            foreach ($allRoles as $roleName) {
                $data = [
                    'name' => $roleName,
                    'guard_name' => $guard,
                ];

                // Add description if available
                if (isset($roleDescriptions[$roleName])) {
                    $data['description'] = $roleDescriptions[$roleName];
                }

                Role::firstOrCreate(
                    ['name' => $roleName, 'guard_name' => $guard],
                    $data
                );

                if ($verbose) {
                    $this->line("  ✓ Role: {$roleName}");
                }
            }

            return true;
        });
    }

    /**
     * Sync permissions from config.
     *
     * @param string $guard
     * @param bool $dry
     * @param bool $verbose
     * @return void
     */
    protected function syncPermissions(string $guard, bool $dry, bool $verbose): void
    {
        $this->components->task('Syncing permissions', function () use ($guard, $dry, $verbose) {
            if ($dry) {
                return 'dry-run';
            }

            $permissionGroups = (array) config('roles.seed.permission_groups', []);
            $permissionLabels = (array) config('roles.seed.permission_labels', []);
            $permissionDescriptions = (array) config('roles.seed.permission_descriptions', []);
            $permissionGroupLabels = (array) config('roles.seed.permission_group_labels', []);

            foreach ($permissionGroups as $group => $actions) {
                foreach ((array) $actions as $action) {
                    $permissionName = "{$group}.{$action}";

                    $data = [
                        'name' => $permissionName,
                        'guard_name' => $guard,
                        'group' => $group,
                    ];

                    // Add group label if available
                    if (isset($permissionGroupLabels[$group])) {
                        $data['group_label'] = $permissionGroupLabels[$group];
                    }

                    // Add label if available
                    if (isset($permissionLabels[$permissionName])) {
                        $data['label'] = $permissionLabels[$permissionName];
                    }

                    // Add description if available
                    if (isset($permissionDescriptions[$permissionName])) {
                        $data['description'] = $permissionDescriptions[$permissionName];
                    }

                    Permission::firstOrCreate(
                        ['name' => $permissionName, 'guard_name' => $guard],
                        $data
                    );

                    if ($verbose) {
                        $this->line("  ✓ Permission: {$permissionName}");
                    }
                }
            }

            return true;
        });
    }

    /**
     * Apply role->permission mapping from config.
     *
     * @param string $guard
     * @param bool $dry
     * @param bool $verbose
     * @return void
     */
    protected function applyMapping(string $guard, bool $dry, bool $verbose): void
    {
        $this->components->task('Applying role->permission mapping', function () use ($guard, $dry, $verbose) {
            if ($dry) {
                // Show what would happen
                $map = (array) config('roles.seed.map', []);
                foreach ($map as $roleName => $patterns) {
                    $expanded = $this->syncService->expandWildcards($patterns, $guard);
                    if ($verbose) {
                        $this->line("  Would assign to {$roleName}: " . count($expanded) . " permissions");
                    }
                }
                return 'dry-run';
            }

            $result = $this->syncService->syncFromConfig(false);

            if ($verbose && !empty($result['synced'])) {
                foreach ($result['synced'] as $sync) {
                    $this->line("  ✓ {$sync['role']}: {$sync['permissions_count']} permissions");
                }
            }

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->warn("  ✗ {$error['role']}: {$error['error']}");
                }
            }

            return true;
        });
    }

    /**
     * Prune permissions not in config.
     *
     * @param string $guard
     * @param bool $dry
     * @return void
     */
    protected function prune(string $guard, bool $dry): void
    {
        $configured = $this->computeConfiguredPermissions($guard);
        $existing = Permission::where('guard_name', $guard)->pluck('name')->all();

        $toDelete = array_values(array_diff($existing, $configured));

        if (empty($toDelete)) {
            $this->components->info('Prune: nothing to remove.');
            return;
        }

        $this->warn('Prune will remove these permissions (and detach from roles):');
        foreach ($toDelete as $p) {
            $this->line("  - {$p}");
        }

        if ($dry) {
            $this->components->info('Dry-run: skipping actual deletion.');
            return;
        }

        // Confirm prune
        if (!$this->confirm('Are you sure you want to prune these permissions?', false)) {
            $this->components->info('Prune cancelled.');
            return;
        }

        DB::transaction(function () use ($toDelete, $guard) {
            foreach ($toDelete as $name) {
                $perm = Permission::where('guard_name', $guard)
                    ->where('name', $name)
                    ->first();

                if (!$perm) {
                    continue;
                }

                try {
                    if (method_exists($perm, 'roles')) {
                        $perm->roles()->detach();
                    }
                    $perm->forceDelete();
                } catch (\Throwable $e) {
                    $this->warn("Could not delete permission '{$name}': {$e->getMessage()}");
                }
            }
        });

        $this->components->info('Prune complete.');
    }

    /**
     * Update labels and descriptions from config.
     *
     * @param string $guard
     * @param bool $dry
     * @param bool $verbose
     * @return void
     */
    protected function updateLabelsAndDescriptions(string $guard, bool $dry, bool $verbose): void
    {
        $this->components->task('Updating labels and descriptions', function () use ($guard, $dry, $verbose) {
            if ($dry) {
                return 'dry-run';
            }

            $permissionLabels = (array) config('roles.seed.permission_labels', []);
            $permissionDescriptions = (array) config('roles.seed.permission_descriptions', []);
            $permissionGroupLabels = (array) config('roles.seed.permission_group_labels', []);

            $updated = 0;

            $permissions = Permission::where('guard_name', $guard)->get();

            foreach ($permissions as $permission) {
                $updates = [];

                // Update label
                if (isset($permissionLabels[$permission->name])) {
                    $updates['label'] = $permissionLabels[$permission->name];
                }

                // Update description
                if (isset($permissionDescriptions[$permission->name])) {
                    $updates['description'] = $permissionDescriptions[$permission->name];
                }

                // Update group label
                if ($permission->group && isset($permissionGroupLabels[$permission->group])) {
                    $updates['group_label'] = $permissionGroupLabels[$permission->group];
                }

                if (!empty($updates)) {
                    $permission->update($updates);
                    $updated++;

                    if ($verbose) {
                        $this->line("  ✓ Updated: {$permission->name}");
                    }
                }
            }

            return $updated > 0 ? "{$updated} updated" : true;
        });
    }

    /**
     * Compute configured permissions from config.
     *
     * @param string $guard
     * @return array
     */
    protected function computeConfiguredPermissions(string $guard): array
    {
        $names = [];

        // Grouped permissions
        foreach ((array) config('roles.seed.permission_groups', []) as $group => $actions) {
            foreach ((array) $actions as $action) {
                $names[] = "{$group}.{$action}";
            }
        }

        return array_values(array_unique($names));
    }
}