<?php

use Illuminate\Support\Facades\Route;
use Enadstack\LaravelRoles\Http\Controllers\UI\RoleUIController;
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
        | Permission Matrix UI Page
        |--------------------------------------------------------------------------
        */
        Route::get('/matrix', [MatrixUIController::class, 'index'])->name('matrix');
    });
