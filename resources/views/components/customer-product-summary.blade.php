@props(['products'])
@inject('presenter', 'App\Presenters\CustomerProductPresenter')
@php($visibleProducts = $presenter->present($products))
@if ($visibleProducts->isNotEmpty())
    <p {{ $attributes->class(['text-sm text-slate-700']) }}>Products: {{ $visibleProducts->map(fn ($product) => $product['label'].' × '.$product['quantity'])->join(', ') }}</p>
@endif
