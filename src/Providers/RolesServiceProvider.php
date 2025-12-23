<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Providers;

use Enadstack\LaravelRoles\Commands\InstallCommand;
use Enadstack\LaravelRoles\Commands\SyncCommand;
use Enadstack\LaravelRoles\Commands\DoctorCommand;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Observers\RoleObserver;
use Enadstack\LaravelRoles\Observers\PermissionObserver;
use Enadstack\LaravelRoles\Listeners\ClearPermissionCache;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;
use Enadstack\LaravelRoles\Events\RoleCreated;
use Enadstack\LaravelRoles\Events\RoleUpdated;
use Enadstack\LaravelRoles\Events\RoleDeleted;
use Enadstack\LaravelRoles\Events\PermissionCreated;
use Enadstack\LaravelRoles\Events\PermissionUpdated;

// Contracts
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Contracts\RoleServiceContract;
use Enadstack\LaravelRoles\Contracts\PermissionServiceContract;
use Enadstack\LaravelRoles\Contracts\RolePermissionSyncServiceContract;
use Enadstack\LaravelRoles\Contracts\PermissionMatrixServiceContract;

// Implementations
use Enadstack\LaravelRoles\Context\SingleTenantContext;
use Enadstack\LaravelRoles\Context\TeamScopedTenantContext;
use Enadstack\LaravelRoles\Context\MultiDatabaseTenantContext;
use Enadstack\LaravelRoles\Context\ConfigGuardResolver;
use Enadstack\LaravelRoles\Context\ContextualCacheKeyBuilder;
use Enadstack\LaravelRoles\Services\RoleService;
use Enadstack\LaravelRoles\Services\PermissionService;
use Enadstack\LaravelRoles\Services\RolePermissionSyncService;
use Enadstack\LaravelRoles\Services\PermissionMatrixService;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

/**
 * RolesServiceProvider
 *
 * Main service provider for the Laravel Roles package.
 * Registers all contracts, implementations, and services.
 *
 * @package Enadstack\LaravelRoles\Providers
 */
class RolesServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(__DIR__ . '/../../config/roles.php', 'roles');

        // Register core contracts
        $this->registerTenantContext();
        $this->registerGuardResolver();
        $this->registerCacheKeyBuilder();

        // Register services
        $this->registerServices();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SyncCommand::class,
                DoctorCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap package services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishAssets();
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'laravel-roles');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/roles.php');

        // Load UI routes conditionally
        $this->loadUIRoutes();

        // Register observers
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);

        // Register event listeners
        $this->registerEventListeners();

        // Apply Spatie context on boot
        $this->applySpatieTenantContext();
    }

    /**
     * Load UI routes if enabled.
     *
     * @return void
     */
    protected function loadUIRoutes(): void
    {
        // Only load UI routes when enabled and driver is 'vue'
        if (
            config('roles.ui.enabled', false) === true &&
            config('roles.ui.driver', 'vue') === 'vue'
        ) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/ui.php');
        }
    }

    /**
     * Register tenant context based on configuration.
     *
     * @return void
     */
    protected function registerTenantContext(): void
    {
        $this->app->singleton(TenantContextContract::class, function ($app) {
            $mode = config('roles.tenancy.mode', 'single');

            return match ($mode) {
                'team_scoped' => new TeamScopedTenantContext(),
                'multi_database' => new MultiDatabaseTenantContext(),
                default => new SingleTenantContext(),
            };
        });

        // Alias for easier resolution
        $this->app->alias(TenantContextContract::class, 'laravel-roles.tenant-context');
    }

    /**
     * Register guard resolver.
     *
     * @return void
     */
    protected function registerGuardResolver(): void
    {
        $this->app->singleton(GuardResolverContract::class, function ($app) {
            return new ConfigGuardResolver();
        });

        $this->app->alias(GuardResolverContract::class, 'laravel-roles.guard-resolver');
    }

    /**
     * Register cache key builder.
     *
     * @return void
     */
    protected function registerCacheKeyBuilder(): void
    {
        $this->app->singleton(CacheKeyBuilderContract::class, function ($app) {
            return new ContextualCacheKeyBuilder(
                $app->make(TenantContextContract::class),
                $app->make(GuardResolverContract::class)
            );
        });

        $this->app->alias(CacheKeyBuilderContract::class, 'laravel-roles.cache-key-builder');
    }

    /**
     * Register application services.
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Role Service
        $this->app->singleton(RoleServiceContract::class, function ($app) {
            return new RoleService(
                $app->make(TenantContextContract::class),
                $app->make(GuardResolverContract::class),
                $app->make(CacheKeyBuilderContract::class)
            );
        });
        $this->app->alias(RoleServiceContract::class, RoleService::class);

        // Permission Service
        $this->app->singleton(PermissionServiceContract::class, function ($app) {
            return new PermissionService(
                $app->make(TenantContextContract::class),
                $app->make(GuardResolverContract::class),
                $app->make(CacheKeyBuilderContract::class)
            );
        });
        $this->app->alias(PermissionServiceContract::class, PermissionService::class);

        // Role Permission Sync Service
        $this->app->singleton(RolePermissionSyncServiceContract::class, function ($app) {
            return new RolePermissionSyncService(
                $app->make(TenantContextContract::class),
                $app->make(GuardResolverContract::class),
                $app->make(CacheKeyBuilderContract::class)
            );
        });
        $this->app->alias(RolePermissionSyncServiceContract::class, RolePermissionSyncService::class);

        // Permission Matrix Service
        $this->app->singleton(PermissionMatrixServiceContract::class, function ($app) {
            return new PermissionMatrixService(
                $app->make(TenantContextContract::class),
                $app->make(GuardResolverContract::class),
                $app->make(CacheKeyBuilderContract::class)
            );
        });
        $this->app->alias(PermissionMatrixServiceContract::class, PermissionMatrixService::class);
    }

    /**
     * Publish package assets.
     *
     * @return void
     */
    protected function publishAssets(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/roles.php' => config_path('roles.php'),
        ], 'roles-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations/' => database_path('migrations'),
        ], 'roles-migrations');

        // Publish translations (if i18n enabled)
        if (config('roles.i18n.enabled')) {
            $this->publishes([
                __DIR__ . '/../../resources/lang' => lang_path('vendor/laravel-roles'),
            ], 'roles-translations');
        }

        // Publish Vue UI pages only
        // Use this for minimal installation - requires laravel-roles-vue-full for dependencies
        $this->publishes([
            __DIR__ . '/../../resources/js/pages' => resource_path('js/Pages/LaravelRoles'),
        ], 'laravel-roles-vue');

        // Publish Vue full package (pages + components + API + composables + types)
        // This is the recommended way for new installations
        $this->publishes([
            __DIR__ . '/../../resources/js/pages' => resource_path('js/Pages/LaravelRoles'),
            __DIR__ . '/../../resources/js/api' => resource_path('js/laravel-roles/api'),
            __DIR__ . '/../../resources/js/composables' => resource_path('js/laravel-roles/composables'),
            __DIR__ . '/../../resources/js/types' => resource_path('js/laravel-roles/types'),
            __DIR__ . '/../../resources/js/components/ui' => resource_path('js/laravel-roles/components'),
            __DIR__ . '/../../resources/js/components/PermissionGroupAccordion.vue' => resource_path('js/laravel-roles/components/PermissionGroupAccordion.vue'),
            __DIR__ . '/../../resources/js/components/PermissionToggleRow.vue' => resource_path('js/laravel-roles/components/PermissionToggleRow.vue'),
        ], 'laravel-roles-vue-full');

        // Publish Vue custom components only (reusable)
        $this->publishes([
            __DIR__ . '/../../resources/js/components/ui' => resource_path('js/laravel-roles/components'),
            __DIR__ . '/../../resources/js/components/PermissionGroupAccordion.vue' => resource_path('js/laravel-roles/components/PermissionGroupAccordion.vue'),
            __DIR__ . '/../../resources/js/components/PermissionToggleRow.vue' => resource_path('js/laravel-roles/components/PermissionToggleRow.vue'),
        ], 'laravel-roles-components');
    }

    /**
     * Register event listeners.
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        Event::listen([
            PermissionsAssignedToRole::class,
            RoleCreated::class,
            RoleUpdated::class,
            RoleDeleted::class,
            PermissionCreated::class,
            PermissionUpdated::class,
        ], ClearPermissionCache::class);
    }

    /**
     * Apply Spatie tenant context on boot.
     *
     * @return void
     */
    protected function applySpatieTenantContext(): void
    {
        $this->app->booted(function () {
            $tenantContext = $this->app->make(TenantContextContract::class);

            // Apply Spatie context if in team_scoped mode
            if ($tenantContext->isTeamScoped()) {
                $tenantContext->applyToSpatie();
            }
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            TenantContextContract::class,
            GuardResolverContract::class,
            CacheKeyBuilderContract::class,
            RoleServiceContract::class,
            PermissionServiceContract::class,
            RolePermissionSyncServiceContract::class,
            PermissionMatrixServiceContract::class,
            'laravel-roles.tenant-context',
            'laravel-roles.guard-resolver',
            'laravel-roles.cache-key-builder',
        ];
    }
}