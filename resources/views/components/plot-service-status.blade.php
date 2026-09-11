@props(['service', 'compact' => false])

@php
    $classes = match ($service->state->tone()) {
        'amber' => 'border-amber-200 bg-amber-50 text-amber-950',
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-950',
        default => 'border-slate-200 bg-slate-50 text-slate-800',
    };
    $dotClasses = match ($service->state->tone()) {
        'amber' => 'bg-amber-500',
        'emerald' => 'bg-emerald-600',
        'sky' => 'bg-sky-600',
        default => 'bg-slate-500',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border ".($compact ? 'px-2 py-2' : 'px-3 py-2.5')." {$classes}"]) }}>
    <div class="flex items-start gap-2">
        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
        <div class="min-w-0">
            <p class="text-sm font-bold leading-5">{{ $service->state->label() }}</p>
            @if ($service->date !== null)
                <p class="mt-1 text-sm font-semibold leading-5">{{ $service->date->format('j M Y') }}</p>
            @endif
        </div>
    </div>
</div>
