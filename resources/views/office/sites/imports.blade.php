<section aria-labelledby="imports-title">
    <h2 id="imports-title" class="section-title">Import History</h2>
    @if (($items['availability'] ?? '') !== 'AVAILABLE')
        <div class="empty-state mt-4"><h3 class="font-bold">Import history is not available yet</h3><p class="mt-2">Import Studio is being prepared. No new import can be started from this page yet.</p></div>
    @else
        <div class="admin-card-grid mt-4">
            @forelse ($items['runs'] as $run)
                <article class="admin-card">
                    <h3 class="admin-card-title">{{ \Illuminate\Support\Carbon::parse($run['export_date'])->format('j M Y') }} · {{ $run['export_slot'] === 'MORNING' ? 'Morning' : 'Afternoon' }}</h3>
                    <p class="mt-3"><span class="status {{ $run['receipt'] ? 'status-green' : 'status-slate' }}">{{ $run['receipt'] ? 'Committed' : 'Not committed' }}</span></p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="admin-term">Uploaded by</dt><dd class="admin-value">{{ $run['uploader_name'] }}</dd></div>
                        <div><dt class="admin-term">Status</dt><dd class="admin-value">{{ ['UPLOADED' => 'Uploaded', 'ANALYSING' => 'Analysis in progress', 'NEEDS_CLARIFICATION' => 'Needs clarification', 'REQUIRES_REVIEW' => 'Ready for review', 'REVIEWED' => 'Reviewed', 'READY_TO_COMMIT' => 'Review approved', 'COMMITTING' => 'Commit in progress', 'COMMITTED' => 'Committed', 'FAILED' => 'Needs attention', 'SUPERSEDED' => 'Superseded'][$run['state']] ?? 'Status unavailable' }}</dd></div>
                        @if ($run['receipt'])
                            <div><dt class="admin-term">Committed</dt><dd class="admin-value">{{ \Illuminate\Support\Carbon::parse($run['receipt']['committed_at'])->utc()->format('j M Y, H:i') }} UTC</dd></div>
                        @endif
                    </dl>
                </article>
            @empty
                <div class="empty-state sm:col-span-2 xl:col-span-3"><h3 class="font-bold">No imports recorded</h3><p class="mt-2">No imports have been recorded for this site.</p></div>
            @endforelse
        </div>
        @if ($items['runs'] instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="mt-5">{{ $items['runs']->links() }}</div>@endif
    @endif
</section>
