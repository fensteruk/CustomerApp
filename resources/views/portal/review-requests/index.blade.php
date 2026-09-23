<x-layouts.portal title="Review Requests | Fenster Customer Portal" sidebar-label="Menu">
    @include('portal.review-requests.styles')
    <section class="rr-workspace" aria-labelledby="page-title">
        <header class="rr-heading"><div><p class="eyebrow">Call-off actions</p><h1 id="page-title">Review Requests</h1><p>Agree requested dates and respond to ordinary call-offs.</p></div><a class="secondary-button" href="{{ route('office.workspace.amendments.index') }}">Review amendments</a></header>
        <p class="rr-note">Date changes are kept in the Amendments workspace, with their earlier request history.</p>
        @if(session('status'))<p class="rr-notice" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="rr-notice" role="alert"><h2>Check your filters</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="GET" action="{{ route('portal.review-requests') }}" class="rr-filters" aria-label="Review request filters">
            <h2 class="sr-only">Review filters</h2>
            <label for="status">Status<select id="status" name="status">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <label for="customer">Customer<select id="customer" name="customer"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)$filters['customer'] === (string)$customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
            <label for="site">Site<select id="site" name="site"><option value="">All sites</option>@foreach($assignedSites as $site)<option value="{{ $site->id }}" @selected((string)$filters['site'] === (string)$site->id)>{{ $site->name }}</option>@endforeach</select></label>
            <label for="service">Service<select id="service" name="service"><option value="">All services</option>@foreach($serviceTypes as $serviceType)<option value="{{ $serviceType->value }}" @selected($filters['service'] === $serviceType->value)>{{ $serviceType->label() }}</option>@endforeach</select></label>
            <label for="search">Search<input id="search" name="search" type="search" maxlength="100" value="{{ $filters['search'] }}" placeholder="Customer, site or plot"></label>
            <label for="date">Requested date<input id="date" name="date" type="date" value="{{ $filters['date'] }}"></label>
            <div class="rr-filter-actions"><button class="primary-button" type="submit">Apply filters</button><a href="{{ route('portal.review-requests') }}">Clear filters</a></div>
        </form>
        <div class="rr-results-heading"><h2>{{ number_format($requests->total()) }} {{ str('request')->plural($requests->total()) }}</h2><span>Newest submissions first</span></div>
        <div class="rr-list">
            @forelse($requests as $callOffRequest)
                <article class="rr-card" aria-labelledby="review-request-{{ $callOffRequest->uuid }}">
                    <div class="rr-card-heading"><div><p>{{ $callOffRequest->batch->site->customerOrganisation->name }} · {{ $callOffRequest->batch->site->name }}</p><h2 id="review-request-{{ $callOffRequest->uuid }}">{{ $callOffRequest->projectedPlot->plot_reference }}</h2></div><span class="status status-{{ $callOffRequest->status->tone() }}">{{ $callOffRequest->status->label() }}</span></div>
                    <dl class="rr-facts"><div><dt>Service type</dt><dd>{{ $callOffRequest->effectiveServiceIdentifier()?->label() ?? 'Not available' }}</dd></div><div><dt>Requested date</dt><dd>{{ $callOffRequest->effectiveRequestedDate()?->format('j M Y') ?? 'Not available' }}</dd>@if($callOffRequest->is_early_date_exception)<span class="rr-early">Early date requested</span>@endif</div><div><dt>Submitting user</dt><dd>{{ $callOffRequest->batch->submittedBy->name }}</dd><time datetime="{{ $callOffRequest->batch->submitted_at->toISOString() }}">{{ $callOffRequest->batch->submitted_at->format('j M Y, H:i') }}</time></div></dl>
                    @if($callOffRequest->batch->customer_response)<p class="rr-note">{{ $callOffRequest->batch->customer_response }}</p>@endif
                    <footer><p>{{ match($callOffRequest->status) { App\Enums\CallOffRequestStatus::AwaitingFenster, App\Enums\CallOffRequestStatus::Submitted => 'Fenster action required', App\Enums\CallOffRequestStatus::AwaitingSiteUser => 'Waiting for customer response', default => 'View the recorded decision and history' } }}</p><a class="secondary-button" href="{{ route('portal.review-requests.show', $callOffRequest) }}">Review request<span class="sr-only"> for {{ $callOffRequest->projectedPlot->plot_reference }} · {{ $callOffRequest->effectiveServiceIdentifier()?->label() }}</span><span aria-hidden="true"> →</span></a></footer>
                </article>
            @empty
                <div class="rr-card rr-empty"><h2>No requests match these filters</h2><p>Try another status or clear the filters. Date changes appear in Amendments.</p><a class="secondary-button" href="{{ route('office.workspace.amendments.index') }}">Go to Amendments</a></div>
            @endforelse
        </div>
        <div class="rr-pagination">{{ $requests->links() }}</div>
    </section>
</x-layouts.portal>
