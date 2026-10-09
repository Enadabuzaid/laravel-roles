<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Users\Http\Controllers\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

// The only public route of the package: marked `access:public` (the doctor allows exactly this name) and throttled per IP.
Route::post('invitations/accept', AcceptInvitationController::class)->name('invitations.accept')
    ->middleware(['access:public', 'throttle:access-invitation-accept']);
