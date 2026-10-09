<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index')->middleware('can:access.invitations.view');
Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store')->middleware('can:access.invitations.create');
Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend')
    ->middleware('can:access.invitations.create')->where('invitation', '[0-9]{1,18}');
Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy')
    ->middleware('can:access.invitations.revoke')->where('invitation', '[0-9]{1,18}');
