<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Models\Role;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleMemberController;
use Enadstack\LaravelRoles\Roles\Http\Controllers\RoleOverviewController;
use Illuminate\Support\Facades\Route;

Route::bind('role', static function (string $value): Role {
    /** @var class-string<Role> $model */
    $model = (string) config('permission.models.role');

    return $model::query()->withTrashed()->findOrFail($value);
});

Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:access.roles.view');
Route::get('roles/overview', RoleOverviewController::class)->name('roles.overview')->middleware('can:access.roles.view');
Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show')->middleware('can:access.roles.view');
Route::get('roles/{role}/members', RoleMemberController::class)->name('roles.members')
    ->middleware(['can:access.roles.view', 'can:access.users.view']);
