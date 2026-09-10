@if ($sourceTimeline->isNotEmpty())
    <section class="mt-6 rounded-xl border border-slate-200 p-5" aria-label="Source completion history">
        <h2 class="section-title">Source completion history</h2>
        <ol class="mt-4 space-y-3">
            @foreach ($sourceTimeline as $entry)
                <li>{{ $entry['label'] }} — {{ $entry['time']->format('j M Y, H:i:s') }} · Source update</li>
            @endforeach
        </ol>
    </section>
@endif
