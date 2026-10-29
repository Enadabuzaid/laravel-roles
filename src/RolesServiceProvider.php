<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles;

use Enadstack\ActionLogger\Models\ActionLog;
use Enadstack\LaravelRoles\Support\Http\ApiExceptionRenderer;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Validated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $rules = array_values(array_unique([
            ...(array) ($package['guardrails']['rules'] ?? []),
            ...(array) config('roles.guardrails.rules', []),
        ]));
        $disabledRules = (array) config('roles.guardrails.disabled', []);

        config(['roles.guardrails.rules' => array_values(array_diff($rules, $disabledRules))]);

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

        $this->app->scoped(Support\ActorContext::class);
        $this->app->singleton(Users\Support\SignInGate::class);
        $this->app->scoped(Audit\AuditRecorder::class);
        $this->app->scoped(Users\Support\HostMfaColumn::class);
        $this->app->scoped(Users\Support\HostUserColumns::class);
        $this->app->scoped(Users\Support\UserDeletionGuard::class);
        $this->app->scoped(Guardrails\Guardrails::class, fn ($app) => new Guardrails\Guardrails(
            array_map(static fn (string $rule): Guardrails\Guardrail => $app->make($rule), array_values((array) config('roles.guardrails.rules', []))),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncCommand::class,
                Console\SeedCommand::class,
                Console\OwnerCommand::class,
                Console\InstallCommand::class,
                Console\DoctorCommand::class,
                Console\BackfillUsersCommand::class,
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
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('access', Http\Middleware\RequireAccess::class);
        $router->aliasMiddleware('access.team', Http\Middleware\SetPermissionsTeam::class);
        $router->aliasMiddleware('access.active', Users\Http\Middleware\EnsureUserIsActive::class);

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'access');
        Event::listen(Validated::class, Users\Listeners\BlockInactiveUser::class);
        Event::listen(Login::class, [Users\Listeners\BlockInactiveUser::class, 'onLogin']);
        Event::listen(Login::class, Users\Listeners\RecordLastLogin::class);
        Event::listen(PasswordReset::class, Users\Listeners\RecordPasswordChange::class);

        RateLimiter::for('access', static function (Request $request): Limit {
            $limit = Http\Support\RoutesRegistrar::rateLimit();
            if ($limit === null) {
                return Limit::none();
            }

            $user = $request->user();

            return Limit::perMinutes($limit['minutes'], $limit['attempts'])->by($user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip());
        });

        RateLimiter::for('access-invitation-accept', static function (Request $request): Limit {
            return Limit::perMinute(max(1, (int) config('roles.users.accept_rate_limit', 6)))->by('invitation-accept|'.$request->ip());
        });

        Gate::before(new Roles\OwnerGate);

        Gate::policy((string) config('permission.models.role'), Roles\Policies\RolePolicy::class);
        Gate::policy(Users\Models\AccessInvitation::class, Users\Policies\InvitationPolicy::class);
        Gate::policy((string) config('permission.models.permission'), Core\Policies\PermissionPolicy::class);
        Gate::policy(ActionLog::class, Audit\Policies\AuditPolicy::class);

        $userModel = (string) config('roles.user_model');
        if ($userModel !== '' && class_exists($userModel) && Gate::getPolicyFor($userModel) === null) {
            Gate::policy($userModel, Users\Policies\UserPolicy::class);
        }

        if (! $this->app->routesAreCached()) {
            Http\Support\RoutesRegistrar::register('roles', __DIR__.'/Roles/routes.php');
            Http\Support\RoutesRegistrar::register('permissions', __DIR__.'/Core/routes.php');
            Http\Support\RoutesRegistrar::register('users', __DIR__.'/Users/routes.php');
            if (config('roles.modules.users')) { // invitations need the users module (state rows); the doctor reports the mismatch
                Http\Support\RoutesRegistrar::register('invitations', __DIR__.'/Users/invitation-routes.php');
                Http\Support\RoutesRegistrar::registerPublic('invitations', __DIR__.'/Users/public-routes.php');
            }
            Http\Support\RoutesRegistrar::register('audit', __DIR__.'/Audit/routes.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/roles.php' => config_path('roles.php')], 'roles-config');
            $this->publishes(
                (new Support\MigrationPublisher(new Filesystem, database_path('migrations')))->targets(),
                'roles-migrations',
            );
            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/access')], 'roles-lang');
            $this->publishes([__DIR__.'/../stubs/permissions.php' => base_path('access/permissions.php')], 'roles-permissions');
        }
    }
}
