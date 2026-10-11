<?php

declare(strict_types=1);

use Enadstack\LaravelRoles\Audit\Http\Controllers\AuditExportController;
use Enadstack\LaravelRoles\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

// The audit log is read-only over HTTP: GET routes only. Static routes come before {entry}.
Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index')->middleware('can:access.audit.view');
Route::get('audit/overview', [AuditLogController::class, 'overview'])->name('audit.overview')->middleware('can:access.audit.view');
Route::get('audit/actors', [AuditLogController::class, 'actors'])->name('audit.actors')->middleware('can:access.audit.view');
Route::get('audit/export', AuditExportController::class)->name('audit.export')
    ->middleware(['can:access.audit.view', 'can:access.reports.export']);
Route::get('audit/{entry}', [AuditLogController::class, 'show'])->name('audit.show')
    ->middleware('can:access.audit.view')->where('entry', '[0-9]{1,18}');
