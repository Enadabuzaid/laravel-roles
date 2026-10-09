<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles;

use Enadstack\LaravelRoles\Support\Http\ApiExceptionRenderer;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/roles.php', 'roles');

        $package = require __DIR__.'/../config/roles.php';
        $checks = array_values(array_unique([
            ...(array) ($package['doctor']['checks'] ?? []),
            ...(array) config('roles.doctor.checks', []),
        ]));
        $disabled = (array) config('roles.doctor.disabled', []);

        config(['roles.doctor.checks' => array_values(array_diff($checks, $disabled))]);

        foreach (['role' => Models\Role::class, 'permission' => Models\Permission::class] as $key => $class) {
            $spatie = $key === 'role' ? Role::class : Permission::class;
            $current = config("permission.models.{$key}");
            if ($current === null || $current === $spatie) {
                config(["permission.models.{$key}" => $class]);
            }
        }

        $this->app->singleton(Core\Registry\PermissionRegistry::class, fn () => new Core\Registry\PermissionRegistry(
            config('roles.permissions_file'),
            array_keys(array_filter((array) config('roles.modules'))),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncCommand::class,
                Console\SeedCommand::class,
                Console\OwnerCommand::class,
                Console\InstallCommand::class,
                Console\DoctorCommand::class,
            ]);
        }

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->renderable(fn (\Throwable $e, Request $request) => ApiExceptionRenderer::render($e, $request));
            }
        });
    }

    public function boot(): void
    {
        Gate::before(new Roles\OwnerGate);

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/roles.php' => config_path('roles.php')], 'roles-config');
            $this->publishes(
                (new Support\MigrationPublisher(new Filesystem, database_path('migrations')))->targets(),
                'roles-migrations',
            );
            $this->publishes([__DIR__.'/../stubs/permissions.php' => base_path('access/permissions.php')], 'roles-permissions');
        }
    }
}
