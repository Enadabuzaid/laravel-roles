<?php


use Illuminate\Support\Facades\Route;
use Enadstack\LaravelRoles\Http\Controllers\RoleController;
use Enadstack\LaravelRoles\Http\Controllers\PermissionController;

$guard = config('roles.guard', config('auth.defaults.guard', 'web'));

// Example: protect with the configured guard
Route::middleware(config('roles.routes.middleware', ['api']))
    ->prefix(config('roles.routes.prefix', 'admin/acl'))
    ->name('roles.')
    ->group(function () {
        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->name('index');
        Route::post('/roles', [RoleController::class, 'store'])->name('store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('show');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('destroy');

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        // Utility: grouped permissions
        Route::get('/permission-groups', [PermissionController::class, 'groups'])->name('permissions.groups');
    });