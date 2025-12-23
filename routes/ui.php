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
| These routes are loaded ONLY when:
|   config('roles.ui.enabled') === true
|   AND config('roles.ui.driver') === 'vue'
|
| These are Inertia routes that render Vue pages.
| All routes use config-based prefixes - no hardcoded URLs.
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
        | Roles UI Pages
        |--------------------------------------------------------------------------
        */
        Route::get('/roles', [RoleUIController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleUIController::class, 'create'])->name('roles.create');
        Route::get('/roles/{id}', [RoleUIController::class, 'show'])->name('roles.show');
        Route::get('/roles/{id}/edit', [RoleUIController::class, 'edit'])->name('roles.edit');

        /*
        |--------------------------------------------------------------------------
        | Permissions UI Pages
        |--------------------------------------------------------------------------
        */
        Route::get('/permissions', [PermissionUIController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/create', [PermissionUIController::class, 'create'])->name('permissions.create');
        Route::get('/permissions/{id}', [PermissionUIController::class, 'show'])->name('permissions.show');
        Route::get('/permissions/{id}/edit', [PermissionUIController::class, 'edit'])->name('permissions.edit');

        /*
        |--------------------------------------------------------------------------
        | Permission Matrix UI Page
        |--------------------------------------------------------------------------
        */
        Route::get('/matrix', [MatrixUIController::class, 'index'])->name('matrix');
    });
