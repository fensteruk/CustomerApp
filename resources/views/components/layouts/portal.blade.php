@props([
    'title' => 'Fenster Customer Portal',
    'sidebarLabel' => 'Menu',
    'sidebarBadge' => null,
])

@include('layouts.portal', [
    'slot' => $slot,
    'title' => $title,
    'sidebar' => $sidebar ?? null,
    'sidebarLabel' => $sidebarLabel,
    'sidebarBadge' => $sidebarBadge,
])
