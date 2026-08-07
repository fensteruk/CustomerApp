<x-layouts.portal title="Confirm Call-Off Action | Fenster Customer Portal">
    <section class="mx-auto max-w-4xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">{{ $activeSite->name }}</p>
                <h1 id="page-title" class="page-title">Confirm {{ $operation === 'withdraw' ? 'withdrawal' : ($operation === 'trash' ? 'move to Trash' : 'restoration') }}</h1>
                <p class="page-intro">Review the exact call-offs below before this change is recorded.</p>
            </div>
            <a href="{{ $operation === 'restore' ? route('portal.call-offs.trash') : route('portal.site-dashboard') }}" class="secondary-button">Cancel</a>
        </div>

        <div class="mt-8 rounded-lg border-2 border-amber-400 bg-amber-50 p-5 text-amber-950" role="alert">
            @if ($operation === 'withdraw')
                <p class="font-bold">These submitted call-offs will be withdrawn. They will no longer remain active, and a five-second Undo option will appear. Withdrawn records may later be moved to Trash. Approved call-offs cannot be withdrawn.</p>
            @elseif ($operation === 'trash')
                <p class="font-bold">Moving these call-offs to Trash removes them from the active dashboard. You can Undo for five seconds or restore them from Trash for seven days. After seven days, they no longer appear in customer-facing Trash, while audit history remains retained.</p>
            @else
                <p class="font-bold">These call-offs will return from Trash with their current underlying status unchanged. A five-second Undo option will appear after restoration.</p>
            @endif
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold text-slate-500">Site</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $activeSite->name }}</dd></div>
                <div><dt class="font-semibold text-slate-500">Selected call-offs</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ $selectedRequests->count() }}</dd></div>
            </dl>
            <ul class="mt-6 space-y-3">
                @foreach ($selectedRequests as $callOffRequest)
                    <li class="rounded-lg bg-slate-50 p-4 text-sm text-slate-800">
                        <strong>{{ $callOffRequest->projectedPlot->plot_reference }}</strong>
                        <span class="block mt-1">{{ $callOffRequest->batch->service_identifier->label() }} · requested {{ $callOffRequest->batch->requested_date->format('j M Y') }} · current status {{ $callOffRequest->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <form method="POST" action="{{ route('portal.call-offs.lifecycle.perform', $operation) }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <input type="hidden" name="operation" value="{{ $operation }}">
            <input type="hidden" name="confirmation_signature" value="{{ $confirmationSignature }}">
            @foreach ($selectedRequests as $callOffRequest)
                <input type="hidden" name="requests[]" value="{{ $callOffRequest->uuid }}">
            @endforeach
            <a href="{{ $operation === 'restore' ? route('portal.call-offs.trash') : route('portal.site-dashboard') }}" class="secondary-button">Cancel</a>
            <button type="submit" class="primary-button" :disabled="submitting" :aria-busy="submitting"><span x-text="submitting ? 'Saving change' : 'Confirm action'"></span></button>
        </form>
    </section>
</x-layouts.portal>
