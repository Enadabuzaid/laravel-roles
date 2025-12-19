<?php

namespace Enadstack\LaravelRoles\Providers;

use Enadstack\LaravelRoles\Commands\InstallCommand;
use Enadstack\LaravelRoles\Commands\SyncCommand;
use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Models\Permission;
use Enadstack\LaravelRoles\Observers\RoleObserver;
use Enadstack\LaravelRoles\Observers\PermissionObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Enadstack\LaravelRoles\Listeners\ClearPermissionCache;
use Enadstack\LaravelRoles\Events\PermissionsAssignedToRole;
use Enadstack\LaravelRoles\Events\RoleCreated;
use Enadstack\LaravelRoles\Events\RoleUpdated;
use Enadstack\LaravelRoles\Events\RoleDeleted;
use Enadstack\LaravelRoles\Events\PermissionCreated;
use Enadstack\LaravelRoles\Events\PermissionUpdated;

class RolesServiceProvider extends  ServiceProvider
{
    public function register(): void
    {
        // Merge package config so users can override via config/roles.php
        $this->mergeConfigFrom(__DIR__ . '/../../config/roles.php', 'roles');
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SyncCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../../config/roles.php' => config_path('roles.php'),
            ], 'roles-config');

            // Publish migrations (if you ship stub migrations)
            $this->publishes([
                __DIR__ . '/../../database/migrations/' => database_path('migrations'),
            ], 'roles-migrations');

            if (config('roles.i18n.enabled')) {
                $this->publishes([
                    __DIR__ . '/../../resources/lang' => lang_path('vendor/laravel-roles'),
                ], 'roles-translations');
            }
        }

        // Load migrations automatically (optional but handy)
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Translations (if any)
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'laravel-roles');

        // Views (if any)
        // $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'laravel-roles');
        // $this->publishes([
        //     __DIR__ . '/../../resources/views' => resource_path('views/vendor/laravel-roles'),
        // ], 'roles-views');

        // Routes (package API)
        $this->loadRoutesFrom(__DIR__ . '/../../routes/roles.php');

        // Register observers for automatic status management
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);

        // Register event listeners for cache clearing and permission cache reset
        Event::listen([
            PermissionsAssignedToRole::class,
            RoleCreated::class,
            RoleUpdated::class,
            RoleDeleted::class,
            PermissionCreated::class,
            PermissionUpdated::class,
        ], ClearPermissionCache::class);
    }
}