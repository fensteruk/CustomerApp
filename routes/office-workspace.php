<?php

use App\Http\Controllers\OfficeAdministrationPageController as Pages;
use App\Http\Controllers\OfficeDemoPurgeController as DemoPurge;
use App\Http\Controllers\OfficePermanentDeletionController as PermanentDeletion;
use App\Http\Controllers\OfficePilotImportController as PilotImport;
use App\Http\Controllers\OfficeUserPageController as UserPages;
use App\Http\Controllers\OfficeWaldSettingsController as WaldSettings;
use App\Models\CustomerOrganisation;
use Illuminate\Support\Facades\Route;

Route::prefix('/portal/office/workspace')->name('office.workspace.')
    ->middleware(['auth', 'active.portal', 'can:viewAny,'.CustomerOrganisation::class])
    ->scopeBindings()->group(function (): void {
        Route::get('/customers', [Pages::class, 'customers'])->name('customers.index');
        Route::get('/users', [UserPages::class, 'index'])->name('users.index');
        Route::get('/users/new', [UserPages::class, 'create'])->name('users.create');
        Route::prefix('/users/{user:uuid}')->whereUuid('user')->group(function (): void {
            Route::get('/', [UserPages::class, 'show'])->name('users.show');
            Route::get('/edit', [UserPages::class, 'edit'])->name('users.edit');
            Route::get('/change-status', [UserPages::class, 'lifecycle'])->name('users.lifecycle');
        });
        Route::get('/customers/new', [Pages::class, 'customerForm'])->name('customers.create');
        Route::get('/imports', [PilotImport::class, 'index'])->name('imports');
        Route::get('/settings/wald', [WaldSettings::class, 'index'])->name('settings.wald');
        Route::put('/settings/wald', [WaldSettings::class, 'update'])->name('settings.wald.update');
        Route::post('/imports/pilot', [PilotImport::class, 'upload'])->name('pilot-import.upload');
        Route::prefix('/imports/pilot/{upload}')->whereUuid('upload')->group(function (): void {
            Route::get('/', [PilotImport::class, 'show'])->name('pilot-import.show');
            Route::post('/confirm-structure', [PilotImport::class, 'confirmStructure'])->name('pilot-import.confirm-structure');
            Route::post('/bindings/draft', [PilotImport::class, 'draftBinding'])->name('pilot-import.bindings.draft');
            Route::post('/bindings/activate', [PilotImport::class, 'activateBinding'])->name('pilot-import.bindings.activate');
            Route::post('/select', [PilotImport::class, 'select'])->name('pilot-import.select');
            Route::prefix('/selections/{selection}')->whereUuid('selection')->group(function (): void {
                Route::post('/analyse', [PilotImport::class, 'analyse'])->name('pilot-import.selections.analyse');
                Route::post('/clarifications', [PilotImport::class, 'answer'])->name('pilot-import.selections.clarifications');
                Route::post('/preview', [PilotImport::class, 'preview'])->name('pilot-import.selections.preview');
                Route::post('/approve', [PilotImport::class, 'approve'])->name('pilot-import.selections.approve');
                Route::post('/commit', [PilotImport::class, 'commit'])->name('pilot-import.selections.commit');
            });
        });
        Route::prefix('/customers/{customerOrganisation:uuid}')->whereUuid('customerOrganisation')->group(function (): void {
            Route::get('/', [Pages::class, 'customer'])->name('customers.show');
            Route::get('/edit', [Pages::class, 'customerForm'])->name('customers.edit');
            Route::get('/change-status', [Pages::class, 'customerLifecycle'])->name('customers.lifecycle');
            Route::get('/delete-permanently', [PermanentDeletion::class, 'customerPreview'])->name('customers.delete-preview');
            Route::post('/delete-permanently', [PermanentDeletion::class, 'customerDelete'])->name('customers.delete');
            Route::get('/purge-demo-data', [DemoPurge::class, 'customerPreview'])->name('customers.demo-purge-preview');
            Route::post('/purge-demo-data', [DemoPurge::class, 'customerPurge'])->name('customers.demo-purge');
            Route::get('/audit', [Pages::class, 'customerAudit'])->name('customers.audit');
            Route::get('/sites/new', [Pages::class, 'siteForm'])->name('sites.create');
            Route::prefix('/sites/{site:uuid}')->whereUuid('site')->group(function (): void {
                Route::get('/', [Pages::class, 'site'])->name('sites.show');
                Route::get('/edit', [Pages::class, 'siteForm'])->name('sites.edit');
                Route::get('/change-status', [Pages::class, 'siteLifecycle'])->name('sites.lifecycle');
                Route::get('/delete-permanently', [PermanentDeletion::class, 'sitePreview'])->name('sites.delete-preview');
                Route::post('/delete-permanently', [PermanentDeletion::class, 'siteDelete'])->name('sites.delete');
                Route::get('/purge-demo-data', [DemoPurge::class, 'sitePreview'])->name('sites.demo-purge-preview');
                Route::post('/purge-demo-data', [DemoPurge::class, 'sitePurge'])->name('sites.demo-purge');
                Route::get('/assign-user', [UserPages::class, 'assignToSite'])->name('sites.assign-user');
            });
        });
    });
