<x-layouts.portal :title="'Delete '.ucfirst($kind).' | Fenster'" sidebar-label="Menu">
    <div class="admin-workspace max-w-2xl">
        <a class="admin-back" href="{{ $cancel }}">← Back to {{ $kind }}</a>
        <header><p class="eyebrow">Permanent deletion</p><h1 class="admin-title [overflow-wrap:anywhere]">Delete {{ $impact['name'] }}?</h1></header>
        <div class="admin-notice mt-5" role="note">
            <p>Deactivate is the normal choice for business records. It keeps access relationships and history. Permanent deletion cannot be undone. There is no automatic demo-data marker; verify this record before continuing.</p>
        </div>
        <section class="admin-card mt-5" aria-labelledby="delete-impact-heading">
            <h2 id="delete-impact-heading" class="section-title">Deletion impact</h2>
            <dl class="mt-3 space-y-2">
                @foreach ($impact['counts'] as $label => $count)
                    <div class="flex justify-between gap-4 border-b border-slate-100 py-1"><dt>{{ Str::headline($label) }}</dt><dd class="font-semibold">{{ $count }}</dd></div>
                @endforeach
            </dl>
        </section>
        @if ($impact['blockers'])
            <section class="admin-error mt-5" role="alert" aria-labelledby="delete-blocked-heading">
                <h2 id="delete-blocked-heading" class="font-bold">Cannot delete yet</h2>
                <p class="mt-2">This record has retained {{ $kind === 'customer' ? 'account, business or source' : 'business or source' }} history. Deactivate it instead. @if ($kind === 'customer')Customer accounts must be handled separately.@endif</p>
                <ul class="mt-3 list-disc pl-5">
                    @foreach ($impact['blockers'] as $label => $count)
                        <li>{{ Str::headline($label) }}: {{ $count }}</li>
                    @endforeach
                </ul>
            </section>
        @else
            <form class="admin-card mt-5 space-y-5" method="POST" action="{{ $action }}">
                @csrf
                <input type="hidden" name="fingerprint" value="{{ $impact['fingerprint'] }}">
                @if ($errors->any())<div class="admin-error" role="alert">{{ $errors->first() }} <a class="underline" href="{{ url()->current() }}">Review again</a></div>@endif
                <label class="flex items-start gap-3"><input class="mt-1 size-5" type="checkbox" name="understood" value="1" required><span>I understand this permanently deletes this {{ $kind }} and its eligible disposable data.</span></label>
                <div><label class="form-label" for="delete-confirmation">Type DELETE to confirm</label><input class="form-input" id="delete-confirmation" name="confirmation" type="text" required autocomplete="off" pattern="DELETE"></div>
                <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5"><button class="reject-button" type="submit">Delete permanently</button><a class="secondary-button" href="{{ $cancel }}">Cancel</a></div>
            </form>
        @endif
    </div>
</x-layouts.portal>
