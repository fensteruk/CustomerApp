@props(['status'])

@php
    $classes = match ($status->tone()) {
        'amber' => 'border-amber-200 bg-amber-50 text-amber-950',
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-950',
        'indigo' => 'border-indigo-300 bg-indigo-50 text-indigo-950',
        default => 'border-slate-200 bg-slate-50 text-slate-900',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-3 py-1.5 text-sm font-bold {$classes}"]) }}>{{ $status->label() }}</span>
