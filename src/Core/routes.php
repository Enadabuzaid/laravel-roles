<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Core\Http\Controllers\AccessCheckController;
use Enadstack\LaravelRoles\Core\Http\Controllers\PermissionController;
use Enadstack\LaravelRoles\Core\Http\Controllers\PermissionModulesController;
use Enadstack\LaravelRoles\Core\Http\Controllers\PermissionOverviewController;
use Enadstack\LaravelRoles\Core\Http\Controllers\SyncPermissionsController;
use Illuminate\Support\Facades\Route;

Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('can:access.permissions.view');
Route::get('permissions/overview', PermissionOverviewController::class)->name('permissions.overview')->middleware('can:access.permissions.view');
Route::get('permissions/modules', PermissionModulesController::class)->name('permissions.modules')->middleware('can:access.permissions.view');
Route::get('permissions/check', AccessCheckController::class)->name('permissions.check')->middleware('can:access.permissions.view');
Route::post('permissions/sync', SyncPermissionsController::class)->name('permissions.sync')->middleware('can:access.permissions.sync');
Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store')->middleware('can:access.permissions.sync');
Route::get('permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show')
    ->middleware('can:access.permissions.view')->where('permission', '[0-9]{1,18}');
Route::match(['put', 'patch'], 'permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update')
    ->middleware('can:access.permissions.sync')->where('permission', '[0-9]{1,18}');
Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy')
    ->middleware('can:access.permissions.sync')->where('permission', '[0-9]{1,18}');
