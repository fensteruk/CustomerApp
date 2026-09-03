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
use App\Http\Controllers\PlotDetailsController;
use App\Http\Controllers\PortalNotificationController;
use App\Http\Controllers\ResubmitRejectedCallOffController;
use App\Http\Controllers\ReviewRequestsController;
use App\Http\Controllers\SiteDashboardController;
use Illuminate\Support\Facades\Route;

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
