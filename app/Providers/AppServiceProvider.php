<?php

namespace App\Providers;

use App\Actions\CallOff\DetermineCallOffEligibilityAction;
use App\Events\CallOffApproved;
use App\Events\CallOffRejected;
use App\Events\CallOffSubmitted;
use App\Listeners\CallOffNotificationListener;
use App\Models\CallOffBatchOperation;
use App\Models\CallOffRequest;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use App\Services\PortalNotificationQueryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.portal', function ($view): void {
            $user = auth()->user();

            $view->with('notificationUnreadCount', $user === null
                ? 0
                : app(PortalNotificationQueryService::class)->unreadCount($user));
        });

        Event::listen(CallOffSubmitted::class, [CallOffNotificationListener::class, 'submitted']);
        Event::listen(CallOffApproved::class, [CallOffNotificationListener::class, 'approved']);
        Event::listen(CallOffRejected::class, [CallOffNotificationListener::class, 'rejected']);

        Gate::define('select-site', fn (User $user, Site $site): bool => $user->isSiteRole() && $user->canAccessSite($site));

        Gate::define('view-site-dashboard', fn (User $user, Site $site): bool => $user->isSiteRole() && $user->canAccessSite($site));

        Gate::define('view-review-requests', fn (User $user): bool => $user->isFensterOfficeStaff());

        Gate::define('view-projected-plot', fn (User $user, ProjectedPlot $plot): bool => app(DetermineCallOffEligibilityAction::class)->canViewProjectedPlot($user, $plot));

        Gate::define('submit-call-off', fn (User $user, Site $site): bool => $user->isSiteRole() && $user->canAccessSite($site));

        Gate::define('review-call-off', fn (User $user, CallOffRequest $request): bool => $user->isFensterOfficeStaff() && $user->canAccessSite($request->batch->site));

        Gate::define('approve-call-off', fn (User $user, CallOffRequest $request): bool => $this->allowsCallOff(fn () => app(DetermineCallOffEligibilityAction::class)->ensureCanApprove($user, $request)));

        Gate::define('reject-call-off', fn (User $user, CallOffRequest $request): bool => $this->allowsCallOff(fn () => app(DetermineCallOffEligibilityAction::class)->ensureCanReject($user, $request)));

        Gate::define('withdraw-call-off', fn (User $user, CallOffRequest $request): bool => $this->allowsCallOff(fn () => app(DetermineCallOffEligibilityAction::class)->ensureCanWithdraw($user, $request)));

        Gate::define('trash-call-off', fn (User $user, CallOffRequest $request): bool => $this->allowsCallOff(fn () => app(DetermineCallOffEligibilityAction::class)->ensureCanTrash($user, $request)));

        Gate::define('restore-call-off', fn (User $user, CallOffRequest $request): bool => $this->allowsCallOff(fn () => app(DetermineCallOffEligibilityAction::class)->ensureCanRestore($user, $request)));

        Gate::define('undo-call-off-operation', fn (User $user, CallOffBatchOperation $operation): bool => $this->allowsCallOff(fn () => app(DetermineCallOffEligibilityAction::class)->ensureCanUndo($user, $operation)));

        Gate::define('resubmit-call-off', fn (User $user, CallOffRequest $request): bool => $this->allowsCallOff(fn () => app(DetermineCallOffEligibilityAction::class)->ensureCanResubmit($user, $request)));
    }

    private function allowsCallOff(callable $check): bool
    {
        try {
            $check();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
