<x-layouts.portal :title="'Purge demo '.ucfirst($kind).' | Fenster'" sidebar-label="Menu">
    <div class="admin-workspace max-w-2xl">
        <a class="admin-back" href="{{ $cancel }}">← Back to {{ $kind }}</a>
        <header><p class="eyebrow">Demo/test data purge</p><h1 class="admin-title [overflow-wrap:anywhere]">Purge demo {{ $kind }} “{{ $impact['name'] }}”?</h1></header>
        <div class="admin-error mt-5" role="note">
            <p class="font-bold">Do not use this action for genuine customer records.</p>
            <p class="mt-2">This permanently removes the listed site-specific data and cannot be undone. No name or prefix automatically proves a record is a test record.</p>
        </div>
        <section class="admin-card mt-5" aria-labelledby="purge-impact-heading">
            <h2 id="purge-impact-heading" class="section-title">Data to remove</h2>
            <dl class="mt-3 space-y-2">
                @foreach ($impact['counts'] as $label => $count)
                    <div class="flex justify-between gap-4 border-b border-slate-100 py-1"><dt class="min-w-0 [overflow-wrap:anywhere]">{{ Str::headline($label) }}</dt><dd class="shrink-0 font-semibold">{{ $count }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-4 text-sm">{{ $impact['retained_uploads'] }} master upload {{ Str::plural('record', $impact['retained_uploads']) }} will be retained with {{ $impact['retained_uploads'] === 1 ? 'its' : 'their' }} private workbook files. User accounts and existing administration audit records are retained.</p>
            @if($includePortalHistory)<p class="mt-2 text-sm font-semibold text-rose-800">This complete customer purge also removes the listed call-off requests, batches, decisions and notifications. Users belonging to this customer will be deactivated and detached; their accounts and attribution remain.</p>@endif
        </section>
        @if ($impact['blockers'])
            <section class="admin-error mt-5" role="alert" aria-labelledby="purge-blocked-heading">
                <h2 id="purge-blocked-heading" class="font-bold">Cannot purge yet</h2>
                <p class="mt-2">The following dependencies need separate handling or belong to another retained record:</p>
                <ul class="mt-3 list-disc pl-5">@foreach ($impact['blockers'] as $label => $count)<li>{{ Str::headline($label) }}: {{ $count }}</li>@endforeach</ul>
                @if($kind === 'customer' && ! $includePortalHistory && $impact['uuid'] === App\Services\DemoPurgeImpact::COMPLETE_HISTORY_CUSTOMER_UUID)<a class="secondary-button mt-4" href="{{ route('office.workspace.customers.demo-purge-preview', ['customerOrganisation' => $impact['uuid'], 'include_portal_history' => 1]) }}">Review complete demo customer purge</a>@endif
            </section>
        @else
            <form class="admin-card mt-5 space-y-5" method="POST" action="{{ $action }}">
                @csrf
                <input type="hidden" name="fingerprint" value="{{ $impact['fingerprint'] }}">
                @if($includePortalHistory)<input type="hidden" name="include_portal_history" value="1">@endif
                @if ($errors->any())<div class="admin-error" role="alert">{{ $errors->first() }} <a class="underline" href="{{ url()->current() }}">Review again</a></div>@endif
                <label class="flex items-start gap-3"><input class="mt-1 size-5 shrink-0" type="checkbox" name="certified_demo" value="1" required><span>I confirm this {{ $kind }} and its associated import data are demo/test data and may be permanently deleted.</span></label>
                @if($includePortalHistory)
                    <label class="flex items-start gap-3"><input class="mt-1 size-5 shrink-0" type="checkbox" name="certified_portal_history" value="1" required><span>I confirm the listed customer requests, call-off history and site data may be permanently removed. A verified recovery point is required before using this in production.</span></label>
                    <div><label class="form-label" for="customer-name-confirmation">Type the exact customer name to confirm</label><input class="form-input" id="customer-name-confirmation" name="customer_name_confirmation" type="text" required autocomplete="off"></div>
                @endif
                <div><label class="form-label" for="purge-confirmation">Type PURGE to confirm</label><input class="form-input" id="purge-confirmation" name="confirmation" type="text" required autocomplete="off" pattern="PURGE"></div>
                <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5"><button class="reject-button" type="submit">Purge demo/test data</button><a class="secondary-button" href="{{ $cancel }}">Cancel</a></div>
            </form>
        @endif
    </div>
</x-layouts.portal>
