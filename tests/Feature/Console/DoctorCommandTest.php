<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Doctor\Check;
use Enadstack\LaravelRoles\Core\Doctor\CheckResult;
use Enadstack\LaravelRoles\Core\Doctor\Checks\ConfigConsistencyCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\RoutesProtectedCheck;
use Enadstack\LaravelRoles\Core\Doctor\Checks\UserModelCheck;
use Enadstack\LaravelRoles\Core\Doctor\CheckStatus;
use Enadstack\LaravelRoles\Core\Registry\PermissionRegistry;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestUser;

beforeEach(function () {
    config(['roles.permissions_file' => dirname(__DIR__, 2).'/Support/registry/valid.php']);
    app()->forgetInstance(PermissionRegistry::class);
});

it('fails when there is no owner and permissions are not synced', function () {
    $this->artisan('roles:doctor')->assertExitCode(1);
});

it('passes after sync, seed and owner bootstrap', function () {
    TestUser::create(['name' => 'A', 'email' => 'boss@example.test', 'password' => 'x']);
    $this->artisan('roles:sync')->assertExitCode(0);
    $this->artisan('roles:seed')->assertExitCode(0);
    $this->artisan('roles:owner boss@example.test')->assertExitCode(0);

    $this->artisan('roles:doctor')->assertExitCode(0);
});

it('fails when a package route has no permission middleware', function () {
    Route::prefix('api/access')->get('open', fn () => 'x');
    TestUser::create(['name' => 'A', 'email' => 'boss@example.test', 'password' => 'x']);
    $this->artisan('roles:sync');
    $this->artisan('roles:seed');
    $this->artisan('roles:owner boss@example.test');

    $this->artisan('roles:doctor')->expectsOutputToContain('api/access/open')->assertExitCode(1);
});

it('warns but does not fail for empty roles', function () {
    TestUser::create(['name' => 'A', 'email' => 'boss@example.test', 'password' => 'x']);
    $this->artisan('roles:sync');
    $this->artisan('roles:seed');
    $this->artisan('roles:owner boss@example.test');

    $this->artisan('roles:doctor')->expectsOutputToContain('admin')->assertExitCode(0);
});

it('passes the user model check for a model with HasAccess', function () {
    $result = app(UserModelCheck::class)->run();

    expect($result->status)->toBe(CheckStatus::Pass)->and($result->message)->toContain(TestUser::class);
});

it('fails the user model check for a plain class or a missing class', function (string $model) {
    config(['roles.user_model' => $model]);

    expect(app(UserModelCheck::class)->run()->status)->toBe(CheckStatus::Fail);
})->with([stdClass::class, 'App\\Missing\\Nope']);

it('does not match every route when the prefix is empty', function () {
    config(['roles.routes.prefix' => '']);
    Route::get('some/open/route', fn () => 'x');

    $result = app(RoutesProtectedCheck::class)->run();

    expect($result->status)->toBe(CheckStatus::Pass)->and($result->message)->toContain('No package routes');
});

it('matches the route prefix on a path segment boundary', function () {
    Route::get('api/accessory/list', fn () => 'x');

    expect(app(RoutesProtectedCheck::class)->run()->message)->toContain('No package routes');

    Route::get('api/access', fn () => 'x');

    expect(app(RoutesProtectedCheck::class)->run()->status)->toBe(CheckStatus::Fail);
});

it('passes the config consistency check with the default config', function () {
    expect(app(ConfigConsistencyCheck::class)->run()->status)->toBe(CheckStatus::Pass);
});

it('fails the config consistency check for teams and non-int id types', function (string $key, mixed $value) {
    config([$key => $value]);

    $result = app(ConfigConsistencyCheck::class)->run();

    expect($result->status)->toBe(CheckStatus::Fail)->and($result->message)->toContain(explode('.', $key)[1]);
})->with([
    'roles.teams' => ['roles.teams', true],
    'permission.teams' => ['permission.teams', true],
    'uuid' => ['roles.id_type', 'uuid'],
    'ulid' => ['roles.id_type', 'ulid'],
]);

it('runs the config consistency check in roles:doctor', function () {
    config(['roles.teams' => true]);

    $this->artisan('roles:doctor')->expectsOutputToContain('config-consistency')->assertExitCode(1);
});

it('reports a throwing check as FAIL instead of crashing', function () {
    config(['roles.doctor.checks' => [ThrowingCheck::class]]);

    $this->artisan('roles:doctor')->expectsOutputToContain('FAIL  throwing — boom')->assertExitCode(1);
});

final class ThrowingCheck implements Check
{
    public function name(): string
    {
        return 'throwing';
    }

    public function run(): CheckResult
    {
        throw new RuntimeException('boom');
    }
}
