<ol class="mt-4 space-y-3" aria-label="Activity history">
    @forelse ($items as $entry)
        <li class="admin-card">
            <div class="flex flex-wrap justify-between gap-2">
                <h3 class="font-bold">{{ ['created' => 'Created', 'renamed' => 'Renamed', 'updated' => 'Details updated', 'deactivated' => 'Deactivated', 'reactivated' => 'Reactivated'][$entry['action']] ?? 'Details changed' }}</h3>
                <time class="text-sm text-slate-500" datetime="{{ $entry['occurred_at'] }}">{{ \Illuminate\Support\Carbon::parse($entry['occurred_at'])->utc()->format('j M Y, H:i') }} UTC</time>
            </div>
            <p class="mt-2 text-sm [overflow-wrap:anywhere]">{{ $entry['actor_name'] }} · {{ \App\Enums\PortalRoleIdentifier::tryFrom($entry['actor_role'])?->label() ?? 'Role not recorded' }}</p>
            @if ($entry['reason'])<p class="mt-3 whitespace-pre-wrap text-sm [overflow-wrap:anywhere]">{{ $entry['reason'] }}</p>@endif
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                @foreach (['name' => 'Name', 'location' => 'Location', 'is_active' => 'Status'] as $field => $label)
                    @if (array_key_exists($field, $entry['after'] ?? []) && (($entry['before'][$field] ?? null) !== $entry['after'][$field]))
                        <div><dt class="admin-term">{{ $label }}</dt><dd class="admin-value">
                            @if (array_key_exists($field, $entry['before'] ?? []))
                                <span class="font-normal">{{ $field === 'is_active' ? ($entry['before'][$field] ? 'Active' : 'Inactive') : ($entry['before'][$field] ?: 'Not set') }}</span> →
                            @endif
                            {{ $field === 'is_active' ? ($entry['after'][$field] ? 'Active' : 'Inactive') : ($entry['after'][$field] ?: 'Not set') }}
                        </dd></div>
                    @endif
                @endforeach
            </dl>
        </li>
    @empty
        <li class="empty-state">No administration activity has been recorded yet. Older records may predate this activity history.</li>
    @endforelse
</ol>
<div class="mt-5">{{ $items->links() }}</div>
