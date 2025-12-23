<?php

use Illuminate\Support\Facades\Route;
use Enadstack\LaravelRoles\Http\Controllers\RoleController;
use Enadstack\LaravelRoles\Http\Controllers\PermissionController;
use Enadstack\LaravelRoles\Http\Controllers\PermissionMatrixController;
use Enadstack\LaravelRoles\Http\Controllers\SelfAclController;

/*
|--------------------------------------------------------------------------
| Laravel Roles Package API Routes
|--------------------------------------------------------------------------
|
| These routes provide the JSON API for the Laravel Roles package.
| They support both session-based (web) and token-based (api) authentication.
|
| Middleware Configuration:
| - Default: ['web', 'auth'] for session-based apps (Inertia/Blade)
| - For API-only: set config('roles.routes.middleware') to ['api', 'auth:sanctum']
|
*/

$prefix = config('roles.routes.prefix', 'admin/acl');
$middleware = config('roles.routes.middleware', ['web', 'auth']);

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('roles.')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Roles CRUD
        |--------------------------------------------------------------------------
        */
        Route::get('/roles', [RoleController::class, 'index'])->name('index');
        Route::post('/roles', [RoleController::class, 'store'])->name('store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('show');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('destroy');

        /*
        |--------------------------------------------------------------------------
        | Roles - Soft Delete Operations
        |--------------------------------------------------------------------------
        */
        Route::post('/roles/{id}/restore', [RoleController::class, 'restore'])->name('restore');
        Route::delete('/roles/{role}/force', [RoleController::class, 'forceDelete'])->name('force-delete');

        /*
        |--------------------------------------------------------------------------
        | Roles - Bulk Operations
        |--------------------------------------------------------------------------
        */
        Route::post('/roles/bulk-delete', [RoleController::class, 'bulkDelete'])->name('bulk-delete');
        Route::post('/roles/bulk-restore', [RoleController::class, 'bulkRestore'])->name('bulk-restore');
        Route::post('/roles/bulk-force-delete', [RoleController::class, 'bulkForceDelete'])->name('bulk-force-delete');

        /*
        |--------------------------------------------------------------------------
        | Roles - Data Endpoints
        |--------------------------------------------------------------------------
        */
        Route::get('/roles-recent', [RoleController::class, 'recent'])->name('recent');
        Route::get('/roles-stats', [RoleController::class, 'stats'])->name('stats');

        /*
        |--------------------------------------------------------------------------
        | Roles - Permission Assignment
        |--------------------------------------------------------------------------
        */
        Route::post('/roles/{role}/permissions', [RoleController::class, 'assignPermissions'])->name('assign-permissions');
        Route::get('/roles/{id}/permissions', [RoleController::class, 'permissions'])->name('permissions');
        Route::get('/roles-permissions', [RoleController::class, 'permissionsGroupedByRole'])->name('permissions-grouped');

        /*
        |--------------------------------------------------------------------------
        | Roles - Permission Diff Sync (NEW in v1.3.0)
        |--------------------------------------------------------------------------
        |
        | Diff-based permission sync endpoint
        | Payload format: { "grant": ["roles.list", "users.create"], "revoke": ["users.delete"] }
        |
        */
        Route::post('/roles/{role}/permissions/diff', [RoleController::class, 'diffPermissions'])->name('permissions.diff');

        /*
        |--------------------------------------------------------------------------
        | Roles - Fine-grained Permission Operations
        |--------------------------------------------------------------------------
        */
        Route::post('/roles/{role}/permission', [RoleController::class, 'addPermission'])->name('permission.attach');
        Route::delete('/roles/{role}/permission', [RoleController::class, 'removePermission'])->name('permission.detach');
        Route::post('/roles/{role}/clone', [RoleController::class, 'clone'])->name('clone');

        /*
        |--------------------------------------------------------------------------
        | Roles - Status Management
        |--------------------------------------------------------------------------
        */
        Route::patch('/roles/{role}/status', [RoleController::class, 'changeStatus'])->name('change-status');
        Route::post('/roles/{role}/activate', [RoleController::class, 'activate'])->name('activate');
        Route::post('/roles/{role}/deactivate', [RoleController::class, 'deactivate'])->name('deactivate');
        Route::post('/roles/bulk-change-status', [RoleController::class, 'bulkChangeStatus'])->name('bulk-change-status');

        /*
        |--------------------------------------------------------------------------
        | Permissions CRUD
        |--------------------------------------------------------------------------
        */
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        /*
        |--------------------------------------------------------------------------
        | Permissions - Soft Delete Operations
        |--------------------------------------------------------------------------
        */
        Route::post('/permissions/{id}/restore', [PermissionController::class, 'restore'])->name('permissions.restore');
        Route::delete('/permissions/{permission}/force', [PermissionController::class, 'forceDelete'])->name('permissions.force-delete');

        /*
        |--------------------------------------------------------------------------
        | Permissions - Bulk Operations
        |--------------------------------------------------------------------------
        */
        Route::post('/permissions/bulk-delete', [PermissionController::class, 'bulkDelete'])->name('permissions.bulk-delete');
        Route::post('/permissions/bulk-restore', [PermissionController::class, 'bulkRestore'])->name('permissions.bulk-restore');
        Route::post('/permissions/bulk-force-delete', [PermissionController::class, 'bulkForceDelete'])->name('permissions.bulk-force-delete');

        /*
        |--------------------------------------------------------------------------
        | Permissions - Data Endpoints
        |--------------------------------------------------------------------------
        */
        Route::get('/permissions-recent', [PermissionController::class, 'recent'])->name('permissions.recent');
        Route::get('/permissions-stats', [PermissionController::class, 'stats'])->name('permissions.stats');

        /*
        |--------------------------------------------------------------------------
        | Permission Matrix (NEW in v1.3.0)
        |--------------------------------------------------------------------------
        |
        | Efficient Role × Permission matrix endpoint
        | Uses ≤ 3 queries, no N+1, cached with tenant/guard/locale awareness
        |
        */
        Route::get('/matrix', [PermissionMatrixController::class, 'index'])->name('matrix');
        Route::get('/matrix/grouped', [PermissionMatrixController::class, 'grouped'])->name('matrix.grouped');

        // Legacy alias
        Route::get('/permissions-matrix', [PermissionController::class, 'matrix'])->name('permissions.matrix');

        /*
        |--------------------------------------------------------------------------
        | Utility: Grouped Permissions
        |--------------------------------------------------------------------------
        */
        Route::get('/permission-groups', [PermissionController::class, 'groups'])->name('permissions.groups');

        /*
        |--------------------------------------------------------------------------
        | Permissions - Status Management
        |--------------------------------------------------------------------------
        */
        Route::patch('/permissions/{permission}/status', [PermissionController::class, 'changeStatus'])->name('permissions.change-status');
        Route::post('/permissions/{permission}/activate', [PermissionController::class, 'activate'])->name('permissions.activate');
        Route::post('/permissions/{permission}/deactivate', [PermissionController::class, 'deactivate'])->name('permissions.deactivate');
        Route::post('/permissions/bulk-change-status', [PermissionController::class, 'bulkChangeStatus'])->name('permissions.bulk-change-status');

        /*
        |--------------------------------------------------------------------------
        | Current User ACL (if enabled)
        |--------------------------------------------------------------------------
        |
        | Expose user's roles, permissions, and tenant scope info
        |
        */
        if (config('roles.routes.expose_me', true)) {
            // Combined ACL endpoint (NEW in v1.3.0)
            Route::get('/me/acl', [SelfAclController::class, 'acl'])->name('me.acl');

            // Individual endpoints (legacy)
            Route::get('/me/roles', [SelfAclController::class, 'roles'])->name('me.roles');
            Route::get('/me/permissions', [SelfAclController::class, 'permissions'])->name('me.permissions');
            Route::get('/me/abilities', [SelfAclController::class, 'abilities'])->name('me.abilities');
        }
    });