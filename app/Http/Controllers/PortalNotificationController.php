<?php

namespace App\Http\Controllers;

use App\Enums\CallOffServiceType;
use App\Enums\PortalNotificationType;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\PortalNotification;
use App\Services\PortalNotificationLinkService;
use App\Services\PortalNotificationQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortalNotificationController extends Controller
{
    public function __construct(
        private readonly PortalNotificationQueryService $notifications,
        private readonly PortalNotificationLinkService $links,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $this->notifications->forUser($user)->limit(50)->get();

        return response()->json([
            'data' => $notifications->map(fn (PortalNotification $notification): array => $this->present($notification)),
            'unread_count' => $this->notifications->unreadCount($user),
        ]);
    }

    public function centre(Request $request): View
    {
        $notifications = $this->notifications
            ->forUser($request->user())
            ->paginate(15)
            ->through(fn (PortalNotification $notification): array => $this->present($notification));

        return view('portal.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->notifications->unreadCount($request->user())]);
    }

    public function read(Request $request, string $notificationUuid): JsonResponse|RedirectResponse
    {
        $this->ensureUuid($notificationUuid);

        if (! $this->notifications->markRead($request->user(), $notificationUuid)) {
            abort(404);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('portal.notifications.centre')
                ->with('status', 'Notification marked as read.');
        }

        return response()->json(['status' => 'read']);
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $markedRead = $this->notifications->markAllRead($request->user());

        if (! $request->expectsJson()) {
            return redirect()
                ->route('portal.notifications.centre')
                ->with('status', $markedRead === 1
                    ? '1 notification marked as read.'
                    : $markedRead.' notifications marked as read.');
        }

        return response()->json(['marked_read' => $markedRead]);
    }

    public function dismiss(Request $request, string $notificationUuid): JsonResponse|RedirectResponse
    {
        $this->ensureUuid($notificationUuid);

        if (! $this->notifications->dismiss($request->user(), $notificationUuid)) {
            abort(404);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('portal.notifications.centre')
                ->with('status', 'Notification dismissed.');
        }

        return response()->json(['status' => 'dismissed']);
    }

    public function open(Request $request, string $notificationUuid): RedirectResponse
    {
        $this->ensureUuid($notificationUuid);
        $notification = $this->notifications->ownedNotification($request->user(), $notificationUuid);

        if ($notification === null) {
            abort(404);
        }

        $target = $this->links->resolve($request->user(), $notification);

        if ($target === null) {
            abort(404);
        }

        $notification->markAsRead();

        if ($target['site_id'] !== null) {
            $request->session()->put(EnsureActiveSiteIsAssigned::SESSION_KEY, $target['site_id']);
        }

        return redirect()->to($target['route']);
    }

    private function ensureUuid(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
    }

    /** @return array<string, mixed> */
    private function present(PortalNotification $notification): array
    {
        $data = $notification->toArray();
        $type = $notification->type;
        $plot = $notification->plot_reference ?? 'the selected plot';
        $site = $notification->site_name ?? 'your assigned site';
        $service = CallOffServiceType::tryFrom((string) $notification->service_identifier)?->label() ?? 'call-off';

        return array_merge($data, [
            'type_label' => $type?->label() ?? 'Portal notification',
            'message' => match ($type) {
                PortalNotificationType::CallOffSubmitted => "Call-off for {$plot} at {$site} was submitted for review.",
                PortalNotificationType::CallOffApproved => "Fenster approved the {$service} call-off for {$plot} at {$site}.",
                PortalNotificationType::CallOffRejected => "Fenster rejected the {$service} call-off for {$plot} at {$site}.",
                default => 'There is an update about one of your call-offs.',
            },
            'open_url' => route('portal.notifications.open', $notification),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_label' => $notification->created_at?->format('j M Y, H:i'),
        ]);
    }
}
