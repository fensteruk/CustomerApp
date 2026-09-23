@if($sidebar !== null)
    <div class="portal-page-tools-wrap">
        <details class="portal-page-tools">
            <summary>
                <span>{{ request()->routeIs('portal.site-dashboard') ? 'Switch site & filters' : 'Page filters' }}</span>
                @if((int) $sidebarBadge > 0)<span class="status status-slate text-xs">{{ $sidebarBadge }} active</span>@endif
            </summary>
            <div class="portal-page-tools-content">{{ $sidebar }}</div>
        </details>
    </div>
@endif