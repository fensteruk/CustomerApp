<section aria-labelledby="imports-title">
    <h2 id="imports-title" class="section-title">Import History</h2>
    @if (($items['availability'] ?? '') !== 'AVAILABLE')
        <div class="empty-state mt-4"><h3 class="font-bold">No import integration has been released yet</h3><p class="mt-2">Import Studio is being prepared. No new import can be started from this page yet.</p></div>
    @else
        <div class="mt-4"><a class="primary-button" href="{{ route('office.workspace.imports', ['site' => $site['uuid']]) }}">Import Source Data</a></div>
        <div class="admin-card-grid mt-4">
            @forelse ($items['runs'] as $run)
                @php
                    $applied = ($run['receipt'] ?? null) !== null;
                    $superseded = $run['is_superseded'] ?? false;
                    $nextAction = match ($run['state']) {
                        'UPLOADED', 'ANALYSING', 'NEEDS_CLARIFICATION', 'FAILED' => 'Continue import',
                        'REQUIRES_REVIEW' => 'Review changes',
                        'REVIEWED' => 'Approve preview',
                        'READY_TO_COMMIT' => 'Apply to CustomerApp',
                        default => 'View import details',
                    };
                @endphp
                <article class="admin-card">
                    <h3 class="admin-card-title">{{ \Illuminate\Support\Carbon::parse($run['export_date'])->format('j M Y') }} · {{ $run['export_slot'] === 'MORNING' ? 'Morning' : 'Afternoon' }}</h3>
                    <p class="mt-3"><span class="status {{ $applied && ! $superseded ? 'status-green' : ($run['state'] === 'FAILED' ? 'status-red' : 'status-slate') }}">{{ $superseded ? 'Superseded' : ($applied ? 'Imported — applied to CustomerApp' : 'Uploaded — not yet applied') }}</span></p>
                    @if(! $applied && ! $superseded)
                        <p class="mt-3 font-semibold text-amber-900">@if($run['state'] === 'NEEDS_CLARIFICATION' && ($run['open_questions'] ?? 0) > 0)Needs your input: {{ $run['open_questions'] }} {{ \Illuminate\Support\Str::plural('clarification', $run['open_questions']) }}@else{{ ['UPLOADED' => 'Ready to analyse', 'ANALYSING' => 'Analysis in progress', 'NEEDS_CLARIFICATION' => 'Analysis needs your attention', 'REQUIRES_REVIEW' => 'Ready to review', 'REVIEWED' => 'Ready to approve', 'READY_TO_COMMIT' => 'Ready to apply', 'FAILED' => 'Analysis failed'][$run['state']] ?? 'Import needs your attention' }}@endif</p>
                        <p class="mt-1 text-sm text-slate-700">No plots from this import have been applied yet.</p>
                    @endif
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="admin-term">Uploaded by</dt><dd class="admin-value">{{ $run['uploader_name'] }}</dd></div>
                        <div><dt class="admin-term">Status</dt><dd class="admin-value">{{ ['UPLOADED' => 'Ready to analyse', 'ANALYSING' => 'Analysis in progress', 'NEEDS_CLARIFICATION' => 'Needs your input', 'REQUIRES_REVIEW' => 'Ready to review', 'REVIEWED' => 'Ready to approve', 'READY_TO_COMMIT' => 'Ready to apply', 'COMMITTING' => 'Applying', 'COMMITTED' => 'Applied', 'FAILED' => 'Failed', 'SUPERSEDED' => 'Superseded'][$run['state']] ?? 'Status unavailable' }}</dd></div>
                        @if ($run['is_correction'] ?? false)<div><dt class="admin-term">Import type</dt><dd class="admin-value">Correction</dd></div>@endif
                        @if ($run['is_superseded'] ?? false)<div><dt class="admin-term">Current record</dt><dd class="admin-value">Superseded by a later import</dd></div>@endif
                        @if (! empty($run['replacement_reason']))<div><dt class="admin-term">Replacement reason</dt><dd class="admin-value">{{ $run['replacement_reason'] }}</dd></div>@endif
                        @if ($applied)
                            <div><dt class="admin-term">Receipt result</dt><dd class="admin-value">Applied to CustomerApp</dd></div>
                            <div><dt class="admin-term">Plots created / existing plots reused</dt><dd class="admin-value">{{ $run['receipt']['plot_counts']['created'] ?? 'Not recorded' }} / {{ $run['receipt']['plot_counts']['reused'] ?? 'Not recorded' }}</dd></div>
                            <div><dt class="admin-term">Applied</dt><dd class="admin-value">{{ \Illuminate\Support\Carbon::parse($run['receipt']['committed_at'])->utc()->format('j M Y, H:i') }} UTC</dd></div>
                            @if (! empty($run['receipt']['counts']))
                                <div><dt class="admin-term">Committed records</dt><dd class="admin-value">{{ collect($run['receipt']['counts'])->map(fn ($count, $label) => str($label)->replace('_', ' ')->title().': '.$count)->join(' · ') }}</dd></div>
                            @endif
                        @endif
                    </dl>
                    @if($superseded)<p class="mt-4 text-sm">Earlier review retained. Any recorded applied result remains in history; open import details for the current revision.</p>@elseif($run['state'] === 'FAILED')<p class="mt-4 text-sm">This site review did not apply partial changes. Open import details for the failure and available recovery options.</p>@endif
                    <div class="mt-5 flex flex-wrap gap-3">
                        @if(! $superseded && ! $applied && ! empty($run['upload_uuid']) && ! empty($run['selection_uuid']))
                            <a class="primary-button" href="{{ route('office.workspace.pilot-import.show', $run['upload_uuid']) }}#selection-{{ $run['selection_uuid'] }}">{{ $nextAction }}</a>
                        @elseif($applied && ! $superseded)
                            <a class="primary-button" href="{{ route('office.workspace.sites.show', ['customerOrganisation' => $site['customer']['uuid'], 'site' => $site['uuid'], 'section' => 'plots']) }}">View plots</a>
                        @endif
                        @if(! empty($run['upload_uuid']) && ! empty($run['selection_uuid']))<a class="secondary-button" href="{{ route('office.workspace.pilot-import.show', $run['upload_uuid']) }}#selection-{{ $run['selection_uuid'] }}">View import details</a>@endif
                    </div>
                </article>
            @empty
                <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="font-bold">No imports recorded</h3><p class="mt-2">No imports have been recorded for this site.</p></div>
            @endforelse
        </div>
        @if ($items['runs'] instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="mt-5">{{ $items['runs']->links() }}</div>@endif
    @endif
</section>
