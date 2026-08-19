<x-layouts.portal title="Trash | Fenster Customer Portal">
    <section class="mx-auto max-w-6xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="eyebrow">{{ $activeSite->name }}</p><h1 id="page-title" class="page-title">Trash</h1><p class="page-intro">Rejected and withdrawn call-offs can be restored for seven days. Expired items remain in audit history but are not shown here.</p></div>
            <a href="{{ route('portal.site-dashboard') }}" class="secondary-button">Back to dashboard</a>
        </div>

        @if (session('status'))<div class="mt-6 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950" role="status">{{ session('status') }}</div>@endif
        @include('portal.call-offs._undo-notice')
        @if ($errors->any())<div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('portal.call-offs.lifecycle.confirm') }}" class="mt-8 space-y-3" x-data="lifecycleSelection()">
            @csrf
            @forelse ($trashedRequests as $callOffRequest)
                <article class="request-card" aria-labelledby="trash-request-{{ $callOffRequest->uuid }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div><h2 id="trash-request-{{ $callOffRequest->uuid }}" class="text-lg font-bold text-slate-900">{{ $callOffRequest->projectedPlot->plot_reference }}</h2><p class="mt-1 text-sm text-slate-600">{{ $callOffRequest->batch->service_identifier->label() }} · requested {{ $callOffRequest->batch->requested_date->format('j M Y') }}</p></div>
                        <span class="status status-{{ $callOffRequest->status->tone() }}">{{ $callOffRequest->status->label() }}</span>
                    </div>
                    <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt>Requested by</dt><dd>{{ $callOffRequest->batch->submittedBy->name }}</dd></div>
                        <div><dt>Moved to Trash</dt><dd>{{ $callOffRequest->trashed_at->format('j M Y, H:i') }}</dd></div>
                        <div><dt>Restore until</dt><dd>{{ $callOffRequest->trash_expires_at->format('j M Y, H:i') }}</dd></div>
                        <div><dt>Customer response</dt><dd>{{ $callOffRequest->histories->first()?->customer_response ?: 'Not provided.' }}</dd></div>
                    </dl>
                    <label class="mt-5 flex min-h-12 items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800"><input type="checkbox" name="requests[]" value="{{ $callOffRequest->uuid }}" data-restore="true" class="h-5 w-5 rounded border-slate-300 text-sky-700 focus:ring-sky-700" @change="update($event)"> Select this call-off to restore</label>
                </article>
            @empty
                <div class="empty-state"><h2 class="text-lg font-bold text-slate-900">Trash is empty</h2><p class="mt-2 text-sm leading-6 text-slate-700">There are no restorable call-offs for this assigned site.</p></div>
            @endforelse

            @if ($trashedRequests->isNotEmpty())
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-slate-700">Selections apply only to this page. Select call-offs from the same submission batch; all selected items must still be restorable.</p>
                    <p class="mt-2 text-sm font-bold text-slate-800" aria-live="polite"><span x-text="count"></span> selected on this page</p>
                    <button type="submit" name="operation" value="restore" class="primary-button mt-4 w-full sm:w-auto" :disabled="!can('restore')" :aria-disabled="(!can('restore')).toString()">Restore selected</button>
                </div>
            @endif
        </form>

        @if ($trashedRequests->hasPages())
            <nav class="mt-6 rounded-lg border border-slate-200 bg-white p-3 shadow-sm" aria-label="Trash pages">
                <p class="mb-3 text-center text-sm font-semibold text-slate-700">Showing {{ $trashedRequests->firstItem() }}–{{ $trashedRequests->lastItem() }} of {{ $trashedRequests->total() }} restorable call-offs</p>
                {{ $trashedRequests->links() }}
            </nav>
        @endif
    </section>
</x-layouts.portal>
