<?php

namespace App\Services;

use App\Models\CallOffRequest;
use App\Models\PortalNotification;
use App\Models\User;

class PortalNotificationLinkService
{
    /** @return array{route: string, site_id: int|null}|null */
    public function resolve(User $user, PortalNotification $notification): ?array
    {
        $request = CallOffRequest::query()
            ->where('uuid', $notification->request_uuid)
            ->with('batch.site')
            ->first();

        if ($request === null || ! $user->hasCompletePortalProfile()) {
            return null;
        }

        if ($notification->route_name === 'portal.review-requests.show') {
            if (! $user->isFensterOfficeStaff()) {
                return null;
            }

            return [
                'route' => route('portal.review-requests.show', $request),
                'site_id' => null,
            ];
        }

        if (in_array($notification->route_name, ['portal.site-dashboard', 'portal.call-offs.show'], true)) {
            if (! $user->isSiteRole() || ! $user->canAccessSite($request->batch->site)) {
                return null;
            }

            return [
                'route' => route('portal.call-offs.show', $request),
                'site_id' => $request->batch->site->id,
            ];
        }

        return null;
    }
}
