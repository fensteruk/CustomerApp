<?php

use App\Http\Controllers\ActiveSiteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CallOffAmendmentController;
use App\Http\Controllers\CallOffDateNegotiationController;
use App\Http\Controllers\CallOffLifecycleController;
use App\Http\Controllers\CallOffRequestDetailsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Development\PreviewRoleController;
use App\Http\Controllers\NewCallOffController;
use App\Http\Controllers\OfficeCustomerController;
use App\Http\Controllers\OfficeSiteController;
use App\Http\Controllers\OfficeSiteUserAssignmentController;
use App\Http\Controllers\OfficeUserController;
use App\Http\Controllers\PlotDetailsController;
use App\Http\Controllers\PortalNotificationController;
use App\Http\Controllers\ResubmitRejectedCallOffController;
use App\Http\Controllers\ReviewRequestsController;
use App\Http\Controllers\SiteDashboardController;
use App\Models\CustomerOrganisation;
use Illuminate\Support\Facades\Route;

require __DIR__.'/office-workspace.php';

if (app()->environment(['local', 'testing']) && config('import-demo.enabled')) {
    require __DIR__.'/import-demo.php';
}

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    if (app()->environment('local')) {
        return redirect()->route('development.role-preview');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active.portal'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/portal/notifications', [PortalNotificationController::class, 'index'])
        ->name('portal.notifications.index');
    Route::get('/portal/notifications/centre', [PortalNotificationController::class, 'centre'])
        ->name('portal.notifications.centre');
    Route::get('/portal/notifications/unread-count', [PortalNotificationController::class, 'unreadCount'])
        ->name('portal.notifications.unread-count');
    Route::get('/portal/notifications/{notificationUuid}/open', [PortalNotificationController::class, 'open'])
        ->whereUuid('notificationUuid')
        ->name('portal.notifications.open');
    Route::post('/portal/notifications/{notificationUuid}/read', [PortalNotificationController::class, 'read'])
        ->whereUuid('notificationUuid')
        ->name('portal.notifications.read');
    Route::post('/portal/notifications/read-all', [PortalNotificationController::class, 'readAll'])
        ->name('portal.notifications.read-all');
    Route::post('/portal/notifications/{notificationUuid}/dismiss', [PortalNotificationController::class, 'dismiss'])
        ->whereUuid('notificationUuid')
        ->name('portal.notifications.dismiss');

    Route::get('/sites/select', [ActiveSiteController::class, 'index'])->name('sites.select');
    Route::post('/sites/active', [ActiveSiteController::class, 'store'])->name('sites.active.store');

    Route::get('/portal/site-dashboard', SiteDashboardController::class)
        ->middleware('active.site')
        ->name('portal.site-dashboard');

    Route::middleware('active.site')->group(function (): void {
        Route::get('/portal/call-offs/{callOffRequest:uuid}/date-change', [CallOffAmendmentController::class, 'create'])->name('portal.call-offs.amendments.create');
        Route::post('/portal/call-offs/{callOffRequest:uuid}/date-change/review', [CallOffAmendmentController::class, 'review'])->name('portal.call-offs.amendments.review');
        Route::post('/portal/call-offs/{callOffRequest:uuid}/date-change', [CallOffAmendmentController::class, 'store'])->name('portal.call-offs.amendments.store');
        Route::get('/portal/plots/{projectedPlot:uuid}', PlotDetailsController::class)
            ->name('portal.plots.show');
        Route::get('/portal/call-offs/new', [NewCallOffController::class, 'create'])
            ->name('portal.call-offs.create');
        Route::get('/portal/call-offs/cavity-closer-date', [NewCallOffController::class, 'cavityCloserDate'])
            ->name('portal.call-offs.cavity-closer-date');
        Route::post('/portal/call-offs/dashboard-selection', [NewCallOffController::class, 'dashboardSelection'])
            ->name('portal.call-offs.dashboard-selection');
        Route::post('/portal/call-offs/matrix', [NewCallOffController::class, 'matrix'])
            ->name('portal.call-offs.matrix');
        Route::post('/portal/call-offs/review', [NewCallOffController::class, 'review'])
            ->name('portal.call-offs.review');
        Route::post('/portal/call-offs', [NewCallOffController::class, 'store'])
            ->name('portal.call-offs.store');
        Route::get('/portal/call-offs/{callOffRequest:uuid}/resubmit', [ResubmitRejectedCallOffController::class, 'create'])
            ->name('portal.call-offs.resubmit.create');
        Route::post('/portal/call-offs/{callOffRequest:uuid}/resubmit/confirm', [ResubmitRejectedCallOffController::class, 'confirm'])
            ->name('portal.call-offs.resubmit.confirm');
        Route::post('/portal/call-offs/{callOffRequest:uuid}/resubmit', [ResubmitRejectedCallOffController::class, 'store'])
            ->name('portal.call-offs.resubmit.store');
        Route::post('/portal/call-offs/{callOffRequest:uuid}/alternative-dates/{callOffDateProposal:uuid}/accept', [CallOffDateNegotiationController::class, 'accept'])
            ->withoutScopedBindings()
            ->name('portal.call-offs.alternative-dates.accept');
        Route::post('/portal/call-offs/{callOffRequest:uuid}/alternative-dates/{callOffDateProposal:uuid}/reject', [CallOffDateNegotiationController::class, 'reject'])
            ->withoutScopedBindings()
            ->name('portal.call-offs.alternative-dates.reject');

        Route::post('/portal/call-offs/lifecycle/confirm', [CallOffLifecycleController::class, 'confirm'])
            ->name('portal.call-offs.lifecycle.confirm');
        Route::post('/portal/call-offs/lifecycle/{operation}', [CallOffLifecycleController::class, 'perform'])
            ->whereIn('operation', ['withdraw', 'trash', 'restore'])
            ->name('portal.call-offs.lifecycle.perform');
        Route::post('/portal/call-offs/operations/{operationUuid}/undo', [CallOffLifecycleController::class, 'undo'])
            ->whereUuid('operationUuid')
            ->name('portal.call-offs.operations.undo');
        Route::get('/portal/call-offs/trash', [CallOffLifecycleController::class, 'trash'])
            ->name('portal.call-offs.trash');
        Route::get('/portal/call-offs/{callOffRequest:uuid}', CallOffRequestDetailsController::class)
            ->name('portal.call-offs.show');
    });

    Route::get('/portal/review-requests', [ReviewRequestsController::class, 'index'])
        ->name('portal.review-requests');
    Route::get('/portal/review-requests/{callOffRequest:uuid}', [ReviewRequestsController::class, 'show'])
        ->name('portal.review-requests.show');
    Route::post('/portal/review-requests/{callOffRequest:uuid}/approve', [ReviewRequestsController::class, 'approve'])
        ->name('portal.review-requests.approve');
    Route::post('/portal/review-requests/{callOffRequest:uuid}/reject', [ReviewRequestsController::class, 'reject'])
        ->name('portal.review-requests.reject');
    Route::post('/portal/review-requests/{callOffRequest:uuid}/agree-requested-date', [CallOffDateNegotiationController::class, 'agree'])
        ->name('portal.review-requests.agree-requested-date');
    Route::post('/portal/review-requests/{callOffRequest:uuid}/alternative-date', [CallOffDateNegotiationController::class, 'propose'])
        ->name('portal.review-requests.propose-alternative-date');

    Route::prefix('/portal/office')
        ->name('portal.office.')
        ->middleware(['can:viewAny,'.CustomerOrganisation::class, 'throttle:office-administration'])
        ->scopeBindings()
        ->group(function (): void {
            Route::post('/users', [OfficeUserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user:uuid}', [OfficeUserController::class, 'update'])->whereUuid('user')->name('users.update');
            Route::post('/users/{user:uuid}/deactivate', [OfficeUserController::class, 'deactivate'])->whereUuid('user')->name('users.deactivate');
            Route::post('/users/{user:uuid}/reactivate', [OfficeUserController::class, 'reactivate'])->whereUuid('user')->name('users.reactivate');
            Route::get('/customers', [OfficeCustomerController::class, 'index'])
                ->name('customers.index');
            Route::post('/customers', [OfficeCustomerController::class, 'store'])
                ->name('customers.store');
            Route::get('/sites', [OfficeSiteController::class, 'index'])
                ->name('sites.index');

            Route::get('/customers/{customerOrganisation:uuid}', [OfficeCustomerController::class, 'show'])
                ->whereUuid('customerOrganisation')
                ->name('customers.show');
            Route::patch('/customers/{customerOrganisation:uuid}', [OfficeCustomerController::class, 'update'])
                ->whereUuid('customerOrganisation')
                ->name('customers.update');
            Route::post('/customers/{customerOrganisation:uuid}/deactivate', [OfficeCustomerController::class, 'deactivate'])
                ->whereUuid('customerOrganisation')
                ->name('customers.deactivate');
            Route::post('/customers/{customerOrganisation:uuid}/reactivate', [OfficeCustomerController::class, 'reactivate'])
                ->whereUuid('customerOrganisation')
                ->name('customers.reactivate');
            Route::get('/customers/{customerOrganisation:uuid}/audit', [OfficeCustomerController::class, 'audits'])
                ->whereUuid('customerOrganisation')
                ->name('customers.audit');

            Route::get('/customers/{customerOrganisation:uuid}/sites', [OfficeSiteController::class, 'customerIndex'])
                ->whereUuid('customerOrganisation')
                ->name('customers.sites.index');
            Route::post('/customers/{customerOrganisation:uuid}/sites', [OfficeSiteController::class, 'store'])
                ->whereUuid('customerOrganisation')
                ->name('customers.sites.store');
            Route::get('/customers/{customerOrganisation:uuid}/sites/{site:uuid}', [OfficeSiteController::class, 'show'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.show');
            Route::patch('/customers/{customerOrganisation:uuid}/sites/{site:uuid}', [OfficeSiteController::class, 'update'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.update');
            Route::post('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/deactivate', [OfficeSiteController::class, 'deactivate'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.deactivate');
            Route::post('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/reactivate', [OfficeSiteController::class, 'reactivate'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.reactivate');
            Route::get('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/plots', [OfficeSiteController::class, 'plots'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.plots');
            Route::get('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/users', [OfficeSiteController::class, 'users'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.users');
            Route::post('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/users', [OfficeSiteUserAssignmentController::class, 'store'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.users.store');
            Route::delete('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/users/{user:uuid}', [OfficeSiteUserAssignmentController::class, 'destroy'])
                ->whereUuid(['customerOrganisation', 'site', 'user'])
                ->withoutScopedBindings()
                ->name('sites.users.destroy');
            Route::get('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/source-binding', [OfficeSiteController::class, 'sourceBindings'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.source-binding');
            Route::get('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/imports', [OfficeSiteController::class, 'importHistory'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.imports');
            Route::get('/customers/{customerOrganisation:uuid}/sites/{site:uuid}/audit', [OfficeSiteController::class, 'audits'])
                ->whereUuid(['customerOrganisation', 'site'])
                ->name('sites.audit');
        });
});

/*
 * These routes are present solely to review the frontend while the access and
 * call-off contracts are being built. The role selector is unavailable outside
 * local/test development and signs in only controlled preview users.
 */
Route::get('/development/role-preview', [PreviewRoleController::class, 'create'])
    ->name('development.role-preview');

Route::post('/development/role-preview', [PreviewRoleController::class, 'store'])
    ->name('development.role-preview.store');
