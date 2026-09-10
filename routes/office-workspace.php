<?php

use App\Http\Controllers\OfficeAdministrationPageController as Pages;
use App\Models\CustomerOrganisation;
use Illuminate\Support\Facades\Route;

Route::prefix('/portal/office/workspace')->name('office.workspace.')
    ->middleware(['auth', 'active.portal', 'can:viewAny,'.CustomerOrganisation::class])
    ->scopeBindings()->group(function (): void {
        Route::get('/customers', [Pages::class, 'customers'])->name('customers.index');
        Route::get('/customers/new', [Pages::class, 'customerForm'])->name('customers.create');
        Route::get('/imports', [Pages::class, 'imports'])->name('imports');
        Route::prefix('/customers/{customerOrganisation:uuid}')->whereUuid('customerOrganisation')->group(function (): void {
            Route::get('/', [Pages::class, 'customer'])->name('customers.show');
            Route::get('/edit', [Pages::class, 'customerForm'])->name('customers.edit');
            Route::get('/change-status', [Pages::class, 'customerLifecycle'])->name('customers.lifecycle');
            Route::get('/audit', [Pages::class, 'customerAudit'])->name('customers.audit');
            Route::get('/sites/new', [Pages::class, 'siteForm'])->name('sites.create');
            Route::prefix('/sites/{site:uuid}')->whereUuid('site')->group(function (): void {
                Route::get('/', [Pages::class, 'site'])->name('sites.show');
                Route::get('/edit', [Pages::class, 'siteForm'])->name('sites.edit');
                Route::get('/change-status', [Pages::class, 'siteLifecycle'])->name('sites.lifecycle');
                Route::get('/import-source-data', [Pages::class, 'imports'])->name('sites.import');
            });
        });
    });
