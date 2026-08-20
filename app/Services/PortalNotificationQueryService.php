<?php

namespace App\Services;

use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PortalNotificationQueryService
{
    /** @return Builder<PortalNotification> */
    public function forUser(User $user): Builder
    {
        return $this->authorisedForUser($user)->active()->latest();
    }

    public function unreadCount(User $user): int
    {
        return $this->forUser($user)->unread()->count();
    }

    public function markRead(User $user, string $uuid): bool
    {
        $notification = $this->ownedNotification($user, $uuid);

        if ($notification === null) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllRead(User $user): int
    {
        return $this->forUser($user)->unread()->update(['read_at' => now()]);
    }

    public function dismiss(User $user, string $uuid): bool
    {
        $notification = $this->ownedNotification($user, $uuid);

        if ($notification === null) {
            return false;
        }

        $notification->dismiss();

        return true;
    }

    public function ownedNotification(User $user, string $uuid): ?PortalNotification
    {
        return $this->authorisedForUser($user)
            ->where('uuid', $uuid)
            ->first();
    }

    /** @return Builder<PortalNotification> */
    private function authorisedForUser(User $user): Builder
    {
        $query = PortalNotification::query()
            ->where('notifiable_user_id', $user->id);

        if (! $user->hasCompletePortalProfile() || $user->is_preview_user) {
            return $query->whereRaw('1 = 0');
        }

        $routeName = $user->isFensterOfficeStaff()
            ? 'portal.review-requests.show'
            : ($user->isSiteRole() ? 'portal.site-dashboard' : null);

        if ($routeName === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('route_name', $routeName);

        if ($user->isFensterOfficeStaff()) {
            return $query;
        }

        return $query->whereHas('request.batch.site', function (Builder $siteQuery) use ($user): void {
            $siteQuery
                ->where('customer_organisation_id', $user->customer_organisation_id)
                ->whereHas('assignedUsers', fn (Builder $assignedUsers): Builder => $assignedUsers->whereKey($user->id));
        });
    }
}
