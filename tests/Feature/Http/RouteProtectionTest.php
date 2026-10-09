<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Doctor\Checks\RoutesProtectedCheck;
use Enadstack\LaravelRoles\Core\Http\Controllers\PermissionController;
use Enadstack\LaravelRoles\Core\Http\Controllers\PermissionModulesController;
use Enadstack\LaravelRoles\Core\Http\Controllers\PermissionOverviewController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\MatrixController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleMemberController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleOverviewController;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/** @return list<LaravelRoute> */
function accessRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        static fn (LaravelRoute $route): bool => str_starts_with((string) $route->getName(), 'access.'),
    ));
}

it('protects every access route with a permission middleware, a 401 envelope and a 403 envelope', function (): void {
    $role = makeRole('target');
    $permission = makePermission('some.thing');
    $target = makeUser('target@example.test');
    $stranger = makeUser('stranger@example.test');
    $stranger->assignRole(makeRole('stranger-role', ['reports.view']));

    $invitation = AccessInvitation::create(['email' => 'inv@example.test', 'token_hash' => hash('sha256', 'inv'), 'expires_at' => now()->addDay()]);

    $routes = accessRoutes();
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $name = (string) $route->getName();

        if ($name === RoutesProtectedCheck::PUBLIC_ROUTE) {
            continue; // the single public route: covered by AcceptInvitationTest and the doctor marker tests
        }

        $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));

        expect(array_filter($middleware, static fn (string $m): bool => str_starts_with($m, 'can:') || str_starts_with($m, 'access:')))->not->toBeEmpty("{$name} has no can:/access: middleware");

        $uri = '/'.str_replace(['{role}', '{permission}', '{user}', '{view}', '{invitation}'], [(string) $role->id, (string) $permission->id, (string) $target->id, '1', (string) $invitation->id], $route->uri());
        $method = $route->methods()[0];

        auth()->forgetGuards();
        $this->json($method, $uri)->assertUnauthorized()->assertJson(['success' => false, 'code' => 'unauthenticated']);

        auth()->forgetGuards();
        $this->actingAs($stranger)->json($method, $uri)->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);
    }
});

it('authorizes the read controllers on their own when a host registers the routes without can middleware', function (): void {
    $role = makeRole('target');
    $stranger = makeUser('stranger@example.test');
    $stranger->assignRole(makeRole('stranger-role', ['reports.view']));

    Route::middleware(['web', 'auth'])->prefix('api/access/bare')->group(function (): void {
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/overview', RoleOverviewController::class);
        Route::get('roles/{role}', [RoleController::class, 'show'])->where('role', '[0-9]+');
        Route::get('roles/{role}/members', RoleMemberController::class)->where('role', '[0-9]+');
        Route::get('matrix', [MatrixController::class, 'show']);
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/modules', PermissionModulesController::class);
        Route::get('permissions/overview', PermissionOverviewController::class);
    });

    foreach (['roles', 'roles/overview', "roles/{$role->id}", "roles/{$role->id}/members", 'matrix', 'permissions', 'permissions/modules', 'permissions/overview'] as $path) {
        $this->actingAs($stranger)->getJson("/api/access/bare/{$path}")->assertForbidden()->assertJson(['success' => false, 'code' => 'forbidden']);
    }

    $viewer = makeUser('viewer@example.test');
    $viewer->assignRole(makeRole('viewer-role', ['access.roles.view', 'access.users.view', 'access.permissions.view']));
    foreach (['roles', 'roles/overview', "roles/{$role->id}", "roles/{$role->id}/members", 'matrix', 'permissions', 'permissions/modules', 'permissions/overview'] as $path) {
        $this->actingAs($viewer)->getJson("/api/access/bare/{$path}")->assertOk();
    }
});
