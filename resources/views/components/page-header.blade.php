@props(['title', 'description' => null, 'eyebrow' => null, 'id' => null])
<header {{ $attributes->class(['portal-page-header']) }}>
    <div class="min-w-0">
        @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1 @if($id) id="{{ $id }}" @endif class="admin-title">{{ $title }}</h1>
        @if($description)<p class="admin-intro">{{ $description }}</p>@endif
        @isset($context)<div class="mt-3">{{ $context }}</div>@endisset
    </div>
    @isset($actions)<div class="admin-actions">{{ $actions }}</div>@endisset
</header>