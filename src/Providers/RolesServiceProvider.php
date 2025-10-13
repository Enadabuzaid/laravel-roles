<?php

namespace Enadstack\LaravelRoles\Providers;

use Enadstack\LaravelRoles\Commands\InstallCommand;
use Enadstack\LaravelRoles\Commands\SyncCommand;
use Illuminate\Support\ServiceProvider;

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
        $this->loadRoutesFrom(__DIR__ . '/../../routes/roles.php');    }
}