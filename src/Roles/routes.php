<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleMemberController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleOverviewController;
use Illuminate\Support\Facades\Route;

Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:access.roles.view');
Route::get('roles/overview', RoleOverviewController::class)->name('roles.overview')->middleware('can:access.roles.view');
Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show')->middleware('can:access.roles.view')
    ->where('role', '[0-9]{1,18}')->withTrashed();
Route::get('roles/{role}/members', RoleMemberController::class)->name('roles.members')
    ->middleware(['can:access.roles.view', 'can:access.users.view'])->where('role', '[0-9]{1,18}')->withTrashed();
Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:access.roles.create');
Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->name('roles.update')
    ->middleware('can:access.roles.update')->where('role', '[0-9]{1,18}');
Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')
    ->middleware('can:access.roles.delete')->where('role', '[0-9]{1,18}');
Route::post('roles/{role}/restore', [RoleController::class, 'restore'])->name('roles.restore')
    ->middleware('can:access.roles.delete')->where('role', '[0-9]{1,18}')->withTrashed();
Route::delete('roles/{role}/force', [RoleController::class, 'force'])->name('roles.force')
    ->middleware('can:access.roles.delete')->where('role', '[0-9]{1,18}')->withTrashed();
