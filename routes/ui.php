<?php

use Illuminate\Support\Facades\Route;
use Enadstack\LaravelRoles\Http\Controllers\UI\RoleUIController;
use Enadstack\LaravelRoles\Http\Controllers\UI\PermissionUIController;
use Enadstack\LaravelRoles\Http\Controllers\UI\MatrixUIController;

/*
|--------------------------------------------------------------------------
| Laravel Roles Package UI Routes
|--------------------------------------------------------------------------
|
| These are Inertia routes that render Vue pages.
| IMPORTANT: UI routes use a /ui sub-prefix to avoid conflicts with API routes.
|
| Routes structure (assuming prefix is 'admin/acl'):
|   /admin/acl/ui/                    -> Roles Management Dashboard
|   /admin/acl/ui/roles               -> Roles List
|   /admin/acl/ui/roles/create        -> Create Role
|   /admin/acl/ui/roles/{id}/edit     -> Edit Role
|   /admin/acl/ui/permissions-management -> Permissions Management Dashboard
|   /admin/acl/ui/permissions         -> Permissions List
|   /admin/acl/ui/matrix              -> Permission Matrix
|
| API routes remain at /admin/acl/roles, /admin/acl/permissions, etc.
|
*/

$prefix = config('roles.ui.prefix', 'admin/acl');
$middleware = config('roles.ui.middleware', ['web', 'auth']);

Route::middleware($middleware)
    ->prefix($prefix . '/ui')
    ->name('roles.ui.')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Roles Management Dashboard (Main Entry)
        |--------------------------------------------------------------------------
        */
        Route::get('/', [RoleUIController::class, 'dashboard'])->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Roles CRUD Pages
        |--------------------------------------------------------------------------
        */
        Route::get('/roles', [RoleUIController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleUIController::class, 'create'])->name('roles.create');
        Route::get('/roles/{id}', [RoleUIController::class, 'show'])->name('roles.show');
        Route::get('/roles/{id}/edit', [RoleUIController::class, 'edit'])->name('roles.edit');

        /*
        |--------------------------------------------------------------------------
        | Permissions Management Dashboard
        |--------------------------------------------------------------------------
        */
        Route::get('/permissions-management', [PermissionUIController::class, 'dashboard'])->name('permissions.dashboard');

        /*
        |--------------------------------------------------------------------------
        | Permissions CRUD Pages
        |--------------------------------------------------------------------------
        */
        Route::get('/permissions', [PermissionUIController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/create', [PermissionUIController::class, 'create'])->name('permissions.create');
        Route::get('/permissions/{id}', [PermissionUIController::class, 'show'])->name('permissions.show');
        Route::get('/permissions/{id}/edit', [PermissionUIController::class, 'edit'])->name('permissions.edit');

        /*
        |--------------------------------------------------------------------------
        | Permission Matrix Page
        |--------------------------------------------------------------------------
        */
        Route::get('/matrix', [MatrixUIController::class, 'index'])->name('matrix');
    });
