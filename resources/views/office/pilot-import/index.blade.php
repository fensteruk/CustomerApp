<x-layouts.portal title="Weekend Pilot Imports | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="eyebrow">Office workspace</p><h1 class="admin-title">Weekend Pilot Imports</h1><p class="page-intro">Upload one RedZebra workbook, then select and review one source site at a time.</p></div>
            <div class="flex flex-wrap gap-2"><span class="status status-red">WEEKEND PILOT</span><span class="status status-slate">OFFICE USE ONLY</span><span class="status status-slate">ONE SITE AT A TIME</span></div>
        </header>
        @if ($errors->any())<div class="mt-5 rounded-xl border-2 border-rose-300 bg-rose-50 p-4 text-rose-950" role="alert"><p class="font-bold">The import was not started.</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if (session('status'))<div class="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 font-semibold text-emerald-950" role="status">{{ session('status') }}</div>@endif

        <section class="admin-card mt-6" aria-labelledby="new-pilot-title">
            <h2 id="new-pilot-title" class="section-title">New supervised import</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">The workbook stays private. Uploading does not change Portal data. You will explicitly bind, preview, approve and commit one site.</p>
            <form method="POST" action="{{ route('office.workspace.pilot-import.upload') }}" enctype="multipart/form-data" class="mt-5 grid gap-5 lg:grid-cols-2">
                @csrf<input type="hidden" name="command_uuid" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                <div class="lg:col-span-2"><label for="workbook" class="form-label">RedZebra workbook</label><input id="workbook" name="workbook" type="file" accept=".xlsx,.csv" required class="form-input mt-1"><p class="mt-2 text-sm text-slate-600">XLSX or CSV, maximum 20 MB. Original filenames are never shown.</p></div>
                <div><label for="export-date" class="form-label">Export date</label><input id="export-date" name="export_date" type="date" required value="{{ old('export_date', now()->format('Y-m-d')) }}" class="form-input mt-1"></div>
                <div><label for="export-slot" class="form-label">Export slot</label><select id="export-slot" name="export_slot" required class="form-input mt-1"><option value="MORNING">Morning</option><option value="AFTERNOON">Afternoon</option></select></div>
                <label class="lg:col-span-2 flex min-h-12 items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 font-semibold text-amber-950"><input type="checkbox" name="confirmation" value="{{ App\SourceImport\Integration\ExportOrder::CONFIRMATION }}" required class="mt-0.5 h-5 w-5"><span>{{ App\SourceImport\Integration\ExportOrder::CONFIRMATION }}</span></label>
                <div class="lg:col-span-2"><button class="primary-button" type="submit">Upload and inspect privately</button></div>
            </form>
        </section>

        <section class="mt-8" aria-labelledby="pilot-history-title"><p class="eyebrow">Private audit history</p><h2 id="pilot-history-title" class="section-title">Pilot uploads</h2>
            <div class="admin-card-grid mt-5">
                @forelse($uploads as $upload)
                    <article class="admin-card"><div class="flex items-start justify-between gap-3"><div><p class="eyebrow">{{ Illuminate\Support\Carbon::parse($upload->export_date)->format('j M Y') }} · {{ str($upload->export_slot)->title() }}</p><h3 class="admin-card-title">RedZebra export</h3></div><span class="status {{ $upload->state === 'FAILED' ? 'status-red' : 'status-slate' }}">{{ str($upload->state)->replace('_', ' ')->title() }}</span></div><p class="mt-4 text-sm">Revision {{ $upload->revision }} · Uploaded by {{ $upload->uploader_name }}</p><a class="secondary-button mt-5 w-full" href="{{ route('office.workspace.pilot-import.show', $upload->uuid) }}">Open supervised review</a></article>
                @empty<div class="empty-state sm:col-span-2"><h3 class="font-bold">No pilot uploads yet</h3><p class="mt-2">Upload the first controlled export above.</p></div>@endforelse
            </div><div class="mt-5">{{ $uploads->links() }}</div>
        </section>
    </div>
</x-layouts.portal>
