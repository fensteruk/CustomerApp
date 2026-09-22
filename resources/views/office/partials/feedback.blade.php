@if ($errors->any())
    <div class="admin-error" role="alert">
        <h2 class="font-bold">Check the information below</h2>
        <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
@if (session('status'))
    <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 font-semibold text-emerald-950" role="status">{{ session('status') }}</div>
@endif
