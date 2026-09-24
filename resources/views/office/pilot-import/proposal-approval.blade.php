<div class="mt-4 rounded-lg border border-sky-300 bg-sky-50 p-4 text-sm" role="group" aria-label="Customer and site proposal">
    <p class="font-semibold text-slate-900">{{ $resolution['state'] === 'EXACT_CUSTOMER_NEW_SITE' ? 'Create a site for an existing customer' : 'Create a customer and site' }}</p>
    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
        <div><dt class="font-medium">Customer</dt><dd class="[overflow-wrap:anywhere]">{{ $resolution['customer'] }}</dd></div>
        <div><dt class="font-medium">Site</dt><dd class="[overflow-wrap:anywhere]">{{ $resolution['site'] }}</dd></div>
        <div><dt class="font-medium">CustomerCode</dt><dd>{{ $source['customer_code'] }}</dd></div>
        <div><dt class="font-medium">Source evidence</dt><dd>{{ $resolution['rows'] }} rows · {{ $resolution['plot_count'] }} plots</dd></div>
    </dl>
    <p class="mt-3 text-slate-700">Example plots: {{ implode(', ', $resolution['example_plots']) ?: 'None listed' }}</p>
    <p class="mt-2 text-slate-700">Wald found {{ $resolution['state'] === 'EXACT_CUSTOMER_NEW_SITE' ? 'this customer but no exact site beneath it' : 'no exact customer match' }}. Confirm the names against the workbook. Approval creates the customer/site structure and source binding; plots are considered later when a site is reviewed and applied.</p>
    @if(in_array($import['state'], ['READY', 'IN_PROGRESS'], true) && ! $selected)
        <form method="POST" action="{{ route('office.workspace.pilot-import.approve-hierarchy', $import['upload']) }}" class="mt-4 space-y-3">
            @csrf
            <input type="hidden" name="source_hash" value="{{ $source['hash'] }}">
            <input type="hidden" name="source_manifest_hash" value="{{ $import['source_manifest_hash'] }}">
            <input type="hidden" name="expected_epoch" value="{{ $import['epoch'] }}">
            <input type="hidden" name="expected_outcome" value="{{ $resolution['state'] }}">
            <input type="hidden" name="expected_customer" value="{{ $resolution['customer'] }}">
            <input type="hidden" name="expected_site" value="{{ $resolution['site'] }}">
            <input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            @if($reviewMode ?? false)<input type="hidden" name="return_to" value="customer-code-review">@endif
            <label class="flex items-start gap-2"><input type="checkbox" name="confirmation" value="APPROVE EXACT CUSTOMER AND SITE" required class="mt-1"><span>I confirm this exact customer and site structure.</span></label>
            <button class="primary-button w-full sm:w-auto" type="submit">{{ $resolution['state'] === 'EXACT_CUSTOMER_NEW_SITE' ? 'Create site' : 'Create customer and site' }}</button>
        </form>
    @endif
</div>
