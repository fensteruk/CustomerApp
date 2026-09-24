<x-layouts.portal title="Choose Call Off Combinations | Fenster Customer Portal">
    <section class="mx-auto max-w-6xl px-4 py-7 sm:px-6 lg:px-8" aria-labelledby="page-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"><div><p class="eyebrow">{{ $activeSite->name }}</p><h1 id="page-title" class="page-title">Check combinations</h1><p class="page-intro">Unavailable combinations remain visible. Exclude any available combination you do not want to submit.</p></div><a href="{{ route('portal.call-offs.create', ['plots' => $plots]) }}" class="secondary-button">Back to edit</a></div>
        @if ($errors->any())<div class="mt-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('portal.call-offs.review') }}" class="mt-8 space-y-4">
            @csrf
            @foreach ($plots as $plot)<input type="hidden" name="plots[]" value="{{ $plot }}">@endforeach
            @foreach ($serviceDates as $service => $date)<input type="hidden" name="service_dates[{{ $service }}]" value="{{ $date }}">@endforeach
            <input type="hidden" name="customer_response" value="{{ $customerResponse }}">
            @foreach ($rows as $row)
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="text-lg font-bold text-slate-950">{{ $row['plot_reference'] }} · {{ App\Enums\CallOffServiceType::from($row['service'])->label() }}</h2><p class="mt-1 text-sm text-slate-600">Requested: {{ \Carbon\CarbonImmutable::parse($row['requested_date'])->format('j M Y') }}@if($row['normal_earliest_date']) · Normal earliest: {{ \Carbon\CarbonImmutable::parse($row['normal_earliest_date'])->format('j M Y') }}@endif</p></div>@if(! $row['available'])<span class="rounded bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">Unavailable</span>@endif</div>
                    <x-customer-product-summary :products="$row['products']" class="mt-3" />
                    @if (! $row['available'])<p class="mt-3 text-sm font-semibold text-slate-700">{{ $row['reason'] }}</p><input type="hidden" name="excluded[]" value="{{ $row['key'] }}">@else<label class="mt-4 flex min-h-11 items-center gap-3 text-sm font-bold text-slate-900"><input type="checkbox" name="excluded[]" value="{{ $row['key'] }}" class="h-5 w-5">Exclude this combination</label>@endif
                    @if ($row['available'] && $row['is_early_exception'])<div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">@if($row['service'] === App\Enums\CallOffServiceType::CavityClosers->value)<p class="text-sm text-amber-950"><strong>Early date request.</strong> Cavity Closers require 15 working days' notice. You selected {{ \Carbon\CarbonImmutable::parse($row['requested_date'])->format('l j F Y') }}; the earliest standard date is {{ \Carbon\CarbonImmutable::parse($row['normal_earliest_date'])->format('l j F Y') }}. This is <strong>{{ $row['working_days_early'] }} working {{ str('day')->plural($row['working_days_early']) }} early</strong>.</p>@endif<label for="reason-{{ md5($row['key']) }}" class="form-label mt-3">Early Date Reason</label><textarea id="reason-{{ md5($row['key']) }}" name="early_reasons[{{ $row['key'] }}]" rows="3" maxlength="2000" class="form-input">{{ old('early_reasons.'.$row['key'], $row['service'] === App\Enums\CallOffServiceType::CavityClosers->value ? $cavityEarlyReason : '') }}</textarea><p class="mt-2 text-sm text-amber-900">A reason is required if this combination remains included.</p></div>@endif
                </article>
            @endforeach
            <div class="flex justify-end"><button type="submit" class="primary-button">Submit</button></div>
        </form>
    </section>
</x-layouts.portal>
