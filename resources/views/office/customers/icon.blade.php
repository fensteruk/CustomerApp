<svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($icon)
        @case('building')
            <path d="M3 21h18M5 21V3h10v18m0-13h4v13M8 7h4M8 11h4M8 15h4m-3 6v-3h2v3" />
            @break
        @case('plots')
            <path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5" />
            @break
        @case('check')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" />
            @break
        @case('attention')
            <path d="m10.3 4-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01" />
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" />
            @break
        @default
            <path d="M4 12h16m-6-6 6 6-6 6" />
    @endswitch
</svg>
