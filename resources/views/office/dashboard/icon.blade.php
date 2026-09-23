<svg class="od-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
    @switch($icon)
        @case('amendment') <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5M14 3l4 4h-4V3ZM8 8h2M8 12h5M8 16h2m4 4 1-4 5-5 3 3-5 5-4 1Z"/> @break
        @case('import') <path d="M7 17H5a4 4 0 0 1-.6-8A7 7 0 0 1 18 8a4.5 4.5 0 0 1 1 9h-2M12 12v9m-3-3 3 3 3-3"/> @break
        @case('sites') <path d="M3 21h18M5 21V3h9v18m0-12h5v12M8 7h3M8 11h3M8 15h3M8 19h3M17 12v1m0 3v1"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M7 3v4m10-4v4"/> @break
        @case('activity') <path d="M2 12h5l3-9 4 18 3-9h5"/> @break
        @case('check') <path d="m5 12 4 4L20 5"/> @break
        @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/> @break
        @default <rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6m-6 4h6m-6 4h4"/>
    @endswitch
</svg>
