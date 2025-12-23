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
| Routes structure:
|   /                  -> Roles Management Dashboard
|   /roles             -> Roles List
|   /roles/create      -> Create Role
|   /roles/{id}/edit   -> Edit Role
|   /permissions-management -> Permissions Management Dashboard
|   /permissions       -> Permissions List
|   /matrix            -> Permission Matrix
|
*/

$prefix = config('roles.ui.prefix', 'admin/acl');
$middleware = config('roles.ui.middleware', ['web', 'auth']);

Route::middleware($middleware)
    ->prefix($prefix)
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
        | Roles CRUD
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
        | Permissions CRUD
        |--------------------------------------------------------------------------
        */
        Route::get('/permissions', [PermissionUIController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/create', [PermissionUIController::class, 'create'])->name('permissions.create');
        Route::get('/permissions/{id}', [PermissionUIController::class, 'show'])->name('permissions.show');
        Route::get('/permissions/{id}/edit', [PermissionUIController::class, 'edit'])->name('permissions.edit');

        /*
        |--------------------------------------------------------------------------
        | Permission Matrix
        |--------------------------------------------------------------------------
        */
        Route::get('/matrix', [MatrixUIController::class, 'index'])->name('matrix');
    });
