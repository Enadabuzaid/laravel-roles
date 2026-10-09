<?php

declare(strict_types=1);

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
