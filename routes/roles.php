<?php


use Illuminate\Support\Facades\Route;
use Enadstack\LaravelRoles\Http\Controllers\RoleController;
use Enadstack\LaravelRoles\Http\Controllers\PermissionController;
// Package routes
$guard = config('roles.guard', config('auth.defaults.guard', 'web'));

// Example: protect with the configured guard
Route::middleware(config('roles.routes.middleware', ['api']))
    ->prefix(config('roles.routes.prefix', 'admin/acl'))
    ->name('roles.')
    ->group(function () {
        // Roles - CRUD
        Route::get('/roles', [RoleController::class, 'index'])->name('index');
        Route::post('/roles', [RoleController::class, 'store'])->name('store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('show');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('destroy');

        // Roles - Advanced operations
        Route::post('/roles/{id}/restore', [RoleController::class, 'restore'])->name('restore');
        Route::delete('/roles/{role}/force', [RoleController::class, 'forceDelete'])->name('force-delete');
        Route::post('/roles/bulk-delete', [RoleController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('/roles/bulk-restore', [RoleController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/roles/bulk-force-delete', [RoleController::class, 'bulkForceDelete'])->name('bulk-force-delete');

        // Roles - Data endpoints
        Route::get('/roles-recent', [RoleController::class, 'recent'])->name('recent');
        Route::get('/roles-stats', [RoleController::class, 'stats'])->name('stats');
        
        // Roles - Permission assignment
        Route::post('/roles/{role}/permissions', [RoleController::class, 'assignPermissions'])->name('assign-permissions');
        Route::get('/roles/{id}/permissions', [RoleController::class, 'permissions'])->name('permissions');
        Route::get('/roles-permissions', [RoleController::class, 'permissionsGroupedByRole'])->name('permissions-grouped');

        // Permissions - CRUD
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        Route::post('/permissions/{id}/restore', [PermissionController::class, 'restore'])->name('permissions.restore');
        Route::post('/permissions/bulk-delete', [PermissionController::class, 'bulkDelete'])->name('permissions.bulk-delete');
        Route::post('/permissions/bulk-restore', [PermissionController::class, 'bulkRestore'])->name('permissions.bulk-restore');
        Route::delete('/permissions/{permission}/force', [PermissionController::class, 'forceDelete'])->name('permissions.force-delete');
        Route::post('/permissions/bulk-force-delete', [PermissionController::class, 'bulkForceDelete'])->name('permissions.bulk-force-delete');

        // Permissions - Data endpoints
        Route::get('/permissions-recent', [PermissionController::class, 'recent'])->name('permissions.recent');
        Route::get('/permissions-stats', [PermissionController::class, 'stats'])->name('permissions.stats');
        Route::get('/permissions-matrix', [PermissionController::class, 'matrix'])->name('permissions.matrix');
        
        // Utility: grouped permissions
        Route::get('/permission-groups', [PermissionController::class, 'groups'])->name('permissions.groups');

        // Optional: endpoints for current user's ACL snapshot
        if (config('roles.routes.expose_me')) {
            Route::get('/me/roles', [\Enadstack\LaravelRoles\Http\Controllers\SelfAclController::class, 'roles'])->name('me.roles');
            Route::get('/me/permissions', [\Enadstack\LaravelRoles\Http\Controllers\SelfAclController::class, 'permissions'])->name('me.permissions');
            Route::get('/me/abilities', [\Enadstack\LaravelRoles\Http\Controllers\SelfAclController::class, 'abilities'])->name('me.abilities');
        }

        // Roles - Fine-grained permission ops and cloning
        Route::post('/roles/{role}/permission', [RoleController::class, 'addPermission'])->name('permission.attach');
        Route::delete('/roles/{role}/permission', [RoleController::class, 'removePermission'])->name('permission.detach');
        Route::post('/roles/{role}/clone', [RoleController::class, 'clone'])->name('clone');
    });