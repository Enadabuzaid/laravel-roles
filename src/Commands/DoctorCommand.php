<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Commands;

use Illuminate\Console\Command;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DoctorCommand
 *
 * Diagnostic command that outputs current configuration and status.
 * Useful for debugging tenancy, guard, and cache issues.
 *
 * @package Enadstack\LaravelRoles\Commands
 */
class DoctorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'roles:doctor
        {--fix : Attempt to fix common issues}
    ';

    /**
     * @var string
     */
    protected $description = 'Diagnose Laravel Roles configuration and system status.';

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
     * Issues found during diagnosis.
     *
     * @var array
     */
    protected array $issues = [];

    /**
     * Create a new command instance.
     *
     * @param TenantContextContract $tenantContext
     * @param GuardResolverContract $guardResolver
     * @param CacheKeyBuilderContract $cacheKeyBuilder
     */
    public function __construct(
        TenantContextContract $tenantContext,
        GuardResolverContract $guardResolver,
        CacheKeyBuilderContract $cacheKeyBuilder
    ) {
        parent::__construct();

        $this->tenantContext = $tenantContext;
        $this->guardResolver = $guardResolver;
        $this->cacheKeyBuilder = $cacheKeyBuilder;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🩺 Laravel Roles Doctor');
        $this->line('');

        // Section 1: Tenancy
        $this->checkTenancy();

        // Section 2: Guard
        $this->checkGuard();

        // Section 3: Cache
        $this->checkCache();

        // Section 4: Database
        $this->checkDatabase();

        // Section 5: Configuration
        $this->checkConfiguration();

        // Section 6: Dependencies
        $this->checkDependencies();

        // Summary
        $this->outputSummary();

        // Attempt fixes if requested
        if ($this->option('fix') && !empty($this->issues)) {
            $this->attemptFixes();
        }

        return empty($this->issues) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Check tenancy configuration.
     *
     * @return void
     */
    protected function checkTenancy(): void
    {
        $this->info('📦 Tenancy Configuration');
        $this->line('');

        $mode = $this->tenantContext->mode();
        $tenantId = $this->tenantContext->tenantId();
        $scopeKey = $this->tenantContext->scopeKey();
        $teamForeignKey = $this->tenantContext->teamForeignKey();

        $this->components->twoColumnDetail('Tenancy Mode', $this->formatStatus($mode, true));
        $this->components->twoColumnDetail('Tenant ID', $tenantId ?? '<comment>N/A</comment>');
        $this->components->twoColumnDetail('Scope Key', $scopeKey);
        $this->components->twoColumnDetail('Team Foreign Key', $teamForeignKey);

        // Validate mode
        $validModes = ['single', 'team_scoped', 'multi_database'];
        if (!in_array($mode, $validModes, true)) {
            $this->addIssue('tenancy', "Invalid tenancy mode: {$mode}. Must be one of: " . implode(', ', $validModes));
        }

        // Check team_scoped specific issues
        if ($mode === 'team_scoped') {
            // Check if team_id column exists
            if (!Schema::hasColumn('roles', $teamForeignKey)) {
                $this->addIssue('tenancy', "Team scoped mode enabled but '{$teamForeignKey}' column not found in roles table.");
            }

            if (!Schema::hasColumn('permissions', $teamForeignKey)) {
                $this->addIssue('tenancy', "Team scoped mode enabled but '{$teamForeignKey}' column not found in permissions table.");
            }

            // Check Spatie teams configuration
            if (!config('permission.teams', false)) {
                $this->addIssue('tenancy', "Team scoped mode enabled but Spatie 'permission.teams' is not enabled.");
            }
        }

        // Check multi_database specific issues
        if ($mode === 'multi_database') {
            $provider = config('roles.tenancy.provider');
            $this->components->twoColumnDetail('Provider', $provider ?? '<comment>Not specified</comment>');

            if (!$provider && !$tenantId) {
                $this->addIssue('tenancy', 'Multi-database mode enabled but no tenant provider configured and no tenant context detected.');
            }
        }

        $this->line('');
    }

    /**
     * Check guard configuration.
     *
     * @return void
     */
    protected function checkGuard(): void
    {
        $this->info('🛡️  Guard Configuration');
        $this->line('');

        $currentGuard = $this->guardResolver->guard();
        $defaultGuard = $this->guardResolver->defaultGuard();
        $availableGuards = $this->guardResolver->availableGuards();

        $this->components->twoColumnDetail('Current Guard', $this->formatStatus($currentGuard, true));
        $this->components->twoColumnDetail('Default Guard', $defaultGuard);
        $this->components->twoColumnDetail('Available Guards', implode(', ', $availableGuards));

        // Check if current guard is valid
        if (!$this->guardResolver->isValidGuard($currentGuard)) {
            $this->addIssue('guard', "Guard '{$currentGuard}' is not defined in auth.guards configuration.");
        }

        // Check for guard mismatches in roles
        $guardMismatches = Role::where('guard_name', '!=', $currentGuard)->count();
        if ($guardMismatches > 0) {
            $this->components->twoColumnDetail('Guard Mismatches (roles)', "<comment>{$guardMismatches} roles with different guard</comment>");
        }

        // Check for guard mismatches in permissions
        $permMismatches = Permission::where('guard_name', '!=', $currentGuard)->count();
        if ($permMismatches > 0) {
            $this->components->twoColumnDetail('Guard Mismatches (permissions)', "<comment>{$permMismatches} permissions with different guard</comment>");
        }

        $this->line('');
    }

    /**
     * Check cache configuration.
     *
     * @return void
     */
    protected function checkCache(): void
    {
        $this->info('💾 Cache Configuration');
        $this->line('');

        $cacheEnabled = $this->cacheKeyBuilder->isEnabled();
        $cacheDriver = config('cache.default');
        $ttl = $this->cacheKeyBuilder->ttl();
        $supportsTags = $this->cacheKeyBuilder->supportsTags();

        $this->components->twoColumnDetail('Cache Enabled', $cacheEnabled ? '<info>Yes</info>' : '<comment>No</comment>');
        $this->components->twoColumnDetail('Cache Driver', $cacheDriver);
        $this->components->twoColumnDetail('TTL (seconds)', (string) $ttl);
        $this->components->twoColumnDetail('Supports Tags', $supportsTags ? '<info>Yes</info>' : '<comment>No</comment>');

        // Check cache key format
        $sampleKey = $this->cacheKeyBuilder->key('sample');
        $this->components->twoColumnDetail('Sample Cache Key', $sampleKey);

        // Test cache functionality
        try {
            $testKey = $this->cacheKeyBuilder->key('doctor_test');
            Cache::put($testKey, 'test', 5);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved === 'test') {
                $this->components->twoColumnDetail('Cache Test', '<info>Passed</info>');
            } else {
                $this->addIssue('cache', 'Cache test failed: retrieved value does not match.');
            }
        } catch (\Throwable $e) {
            $this->addIssue('cache', "Cache test failed: {$e->getMessage()}");
        }

        // Check for potential tag issues with non-supporting drivers
        if (!$supportsTags && $cacheEnabled) {
            $this->components->warn("  ⚠️  Cache driver '{$cacheDriver}' does not support tags. Using key-based invalidation.");
        }

        $this->line('');
    }

    /**
     * Check database status.
     *
     * @return void
     */
    protected function checkDatabase(): void
    {
        $this->info('🗄️  Database Status');
        $this->line('');

        try {
            // Check connection
            DB::connection()->getPdo();
            $this->components->twoColumnDetail('Database Connection', '<info>Connected</info>');
        } catch (\Throwable $e) {
            $this->addIssue('database', "Database connection failed: {$e->getMessage()}");
            $this->components->twoColumnDetail('Database Connection', '<error>Failed</error>');
            return;
        }

        // Check tables exist
        $tablesExist = Schema::hasTable('roles') && Schema::hasTable('permissions');
        $this->components->twoColumnDetail('Required Tables', $tablesExist ? '<info>Present</info>' : '<error>Missing</error>');

        if (!$tablesExist) {
            $this->addIssue('database', 'Required tables (roles, permissions) not found. Run migrations.');
            return;
        }

        // Check soft delete columns
        $hasSoftDeletes = Schema::hasColumn('roles', 'deleted_at') && Schema::hasColumn('permissions', 'deleted_at');
        $this->components->twoColumnDetail('Soft Deletes', $hasSoftDeletes ? '<info>Enabled</info>' : '<comment>Disabled</comment>');

        // Check status column
        $hasStatus = Schema::hasColumn('roles', 'status') && Schema::hasColumn('permissions', 'status');
        $this->components->twoColumnDetail('Status Column', $hasStatus ? '<info>Present</info>' : '<comment>Missing</comment>');

        // Check i18n columns
        $hasI18n = Schema::hasColumn('permissions', 'label') && Schema::hasColumn('permissions', 'description');
        $this->components->twoColumnDetail('i18n Columns', $hasI18n ? '<info>Present</info>' : '<comment>Missing</comment>');

        // Counts
        $this->components->twoColumnDetail('Total Roles', (string) Role::count());
        $this->components->twoColumnDetail('Total Permissions', (string) Permission::count());

        $this->line('');
    }

    /**
     * Check configuration.
     *
     * @return void
     */
    protected function checkConfiguration(): void
    {
        $this->info('⚙️  Configuration Validation');
        $this->line('');

        // Check if config is published
        $configPath = config_path('roles.php');
        $configPublished = file_exists($configPath);
        $this->components->twoColumnDetail('Config Published', $configPublished ? '<info>Yes</info>' : '<comment>No (using package defaults)</comment>');

        // Check i18n configuration
        $i18nEnabled = config('roles.i18n.enabled', false);
        $this->components->twoColumnDetail('i18n Enabled', $i18nEnabled ? '<info>Yes</info>' : '<comment>No</comment>');

        if ($i18nEnabled) {
            $locales = config('roles.i18n.locales', ['en']);
            $this->components->twoColumnDetail('Configured Locales', implode(', ', $locales));
        }

        // Check routes configuration
        $routePrefix = config('roles.routes.prefix', 'admin/acl');
        $routeMiddleware = config('roles.routes.middleware', []);
        $this->components->twoColumnDetail('Route Prefix', $routePrefix);
        $this->components->twoColumnDetail('Route Middleware', implode(', ', $routeMiddleware));

        // Check seeds configuration
        $roleSeeds = config('roles.seed.roles', []);
        $permissionGroups = config('roles.seed.permission_groups', []);
        $this->components->twoColumnDetail('Seed Roles', (string) count($roleSeeds));
        $this->components->twoColumnDetail('Permission Groups', (string) count($permissionGroups));

        // Check mapping
        $map = config('roles.seed.map', []);
        $this->components->twoColumnDetail('Role->Permission Maps', (string) count($map));

        $this->line('');
    }

    /**
     * Check dependencies.
     *
     * @return void
     */
    protected function checkDependencies(): void
    {
        $this->info('📦 Dependencies');
        $this->line('');

        // Check Spatie Permission
        $spatieInstalled = class_exists(\Spatie\Permission\PermissionRegistrar::class);
        $this->components->twoColumnDetail('Spatie Permission', $spatieInstalled ? '<info>Installed</info>' : '<error>Not installed</error>');

        if (!$spatieInstalled) {
            $this->addIssue('dependencies', 'Spatie Laravel Permission is not installed.');
        }

        // Check for optional multi-tenancy packages
        $stanclTenancy = class_exists('\Stancl\Tenancy\Tenancy');
        $spatieMultitenancy = class_exists('\Spatie\Multitenancy\Models\Tenant');

        if ($this->tenantContext->isMultiDatabase()) {
            if (!$stanclTenancy && !$spatieMultitenancy) {
                $this->components->twoColumnDetail('Multi-tenancy Package', '<comment>None detected</comment>');
                $this->addIssue('dependencies', 'Multi-database mode enabled but no known tenancy package detected.');
            } elseif ($stanclTenancy) {
                $this->components->twoColumnDetail('Multi-tenancy Package', '<info>stancl/tenancy</info>');
            } elseif ($spatieMultitenancy) {
                $this->components->twoColumnDetail('Multi-tenancy Package', '<info>spatie/laravel-multitenancy</info>');
            }
        }

        $this->line('');
    }

    /**
     * Add an issue.
     *
     * @param string $category
     * @param string $message
     * @return void
     */
    protected function addIssue(string $category, string $message): void
    {
        $this->issues[] = [
            'category' => $category,
            'message' => $message,
        ];
    }

    /**
     * Output summary.
     *
     * @return void
     */
    protected function outputSummary(): void
    {
        $this->info('📋 Summary');
        $this->line('');

        if (empty($this->issues)) {
            $this->components->info('✅ All checks passed! No issues detected.');
        } else {
            $this->components->warn('⚠️  Found ' . count($this->issues) . ' issue(s):');
            $this->line('');

            foreach ($this->issues as $issue) {
                $this->error("  [{$issue['category']}] {$issue['message']}");
            }

            $this->line('');
            $this->components->info('Run with --fix to attempt automatic fixes.');
        }

        $this->line('');
    }

    /**
     * Attempt to fix issues.
     *
     * @return void
     */
    protected function attemptFixes(): void
    {
        $this->info('🔧 Attempting Fixes');
        $this->line('');

        foreach ($this->issues as $issue) {
            $fixed = $this->attemptFix($issue);

            if ($fixed) {
                $this->components->info("✓ Fixed: {$issue['message']}");
            } else {
                $this->components->warn("✗ Could not fix: {$issue['message']}");
            }
        }

        $this->line('');
    }

    /**
     * Attempt to fix a single issue.
     *
     * @param array $issue
     * @return bool
     */
    protected function attemptFix(array $issue): bool
    {
        switch ($issue['category']) {
            case 'cache':
                // Clear all caches
                $this->cacheKeyBuilder->flushAll();
                return true;

            case 'tenancy':
                // Can't auto-fix tenancy issues
                return false;

            case 'guard':
                // Can't auto-fix guard issues
                return false;

            case 'database':
                // Suggest running migrations
                $this->comment('  Run: php artisan migrate');
                return false;

            case 'dependencies':
                // Can't auto-fix dependencies
                return false;

            default:
                return false;
        }
    }

    /**
     * Format status for display.
     *
     * @param string $value
     * @param bool $success
     * @return string
     */
    protected function formatStatus(string $value, bool $success): string
    {
        return $success ? "<info>{$value}</info>" : "<error>{$value}</error>";
    }
}
