@if ($errors->any())
    <div class="admin-error" role="alert">
        <h2 class="font-bold">Check the information below</h2>
        <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
