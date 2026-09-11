<?php

use App\Http\Controllers\Development\SyntheticImportStudioController;
use Illuminate\Support\Facades\Route;

Route::prefix('/development/import-studio')
    ->name('development.import-studio.')
    ->middleware(['auth', 'active.portal'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', [SyntheticImportStudioController::class, 'show'])->name('show');
        Route::get('/customers/{customerOrganisation:uuid}/sites/{site:uuid}', [SyntheticImportStudioController::class, 'site'])
            ->whereUuid(['customerOrganisation', 'site'])
            ->name('site');
    });
