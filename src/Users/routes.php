<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Http\Controllers\SavedViewController;
use Enadstack\LaravelRoles\Users\Http\Controllers\UserController;
use Enadstack\LaravelRoles\Users\Http\Controllers\UserLifecycleController;
use Enadstack\LaravelRoles\Users\Http\Controllers\UserOverviewController;
use Enadstack\LaravelRoles\Users\Http\Controllers\UserPermissionsController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('can:access.users.view');
Route::get('users/overview', UserOverviewController::class)->name('users.overview')->middleware('can:access.users.view');
Route::get('users/{user}', [UserController::class, 'show'])->name('users.show')
    ->middleware('can:access.users.view')->where('user', '[0-9]{1,18}');
Route::get('users/{user}/permissions', UserPermissionsController::class)->name('users.permissions')
    ->middleware('can:access.users.view')->where('user', '[0-9]{1,18}');

foreach ([
    ['suspend', 'suspend', 'access.users.suspend'],
    ['reactivate', 'reactivate', 'access.users.suspend'],
    ['deactivate', 'deactivate', 'access.users.suspend'],
    ['unlock', 'unlock', 'access.users.update'],
    ['force-password-reset', 'forcePasswordReset', 'access.users.update'],
] as [$uri, $method, $permission]) {
    Route::post("users/{user}/{$uri}", [UserLifecycleController::class, $method])->name("users.{$uri}")
        ->middleware("can:{$permission}")->where('user', '[0-9]{1,18}');
}
Route::delete('users/{user}', [UserLifecycleController::class, 'destroy'])->name('users.destroy')
    ->middleware('can:access.users.delete')->where('user', '[0-9]{1,18}');

Route::get('saved-views', [SavedViewController::class, 'index'])->name('saved-views.index')->middleware('can:access.users.view');
Route::post('saved-views', [SavedViewController::class, 'store'])->name('saved-views.store')->middleware('can:access.users.view');
Route::delete('saved-views/{view}', [SavedViewController::class, 'destroy'])->name('saved-views.destroy')
    ->middleware('can:access.users.view')->where('view', '[0-9]{1,18}');
