<x-layouts.portal title="Wald Settings | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="eyebrow">Imports &amp; Integrations</p><h1 class="admin-title">Wald Import Pilot</h1><p class="page-intro">Control the supervised Office-only spreadsheet import pilot.</p></div>
            <div class="flex flex-wrap gap-2"><span class="status status-red">WEEKEND PILOT / SUPERVISED USE</span><span class="status {{ $effective ? 'status-green' : 'status-slate' }}">{{ $effective ? 'Available' : 'Unavailable' }}</span></div>
        </header>

        @if (session('status'))<div class="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 font-semibold text-emerald-950" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="mt-5 rounded-xl border-2 border-rose-300 bg-rose-50 p-4 text-rose-950" role="alert"><p class="font-bold">The setting was not changed.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="admin-card mt-6" aria-labelledby="wald-control-title">
            <h2 id="wald-control-title" class="section-title">Enable Wald Import Pilot</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">When enabled, authorised Fenster Office Staff can use the supervised Wald spreadsheet import pilot. Customer users never receive access.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4"><p class="eyebrow">Environment gate</p><p class="mt-1 font-bold">{{ $environmentAllows ? 'Allows Wald' : 'Emergency OFF' }}</p></div>
                <div class="rounded-xl bg-slate-50 p-4"><p class="eyebrow">Application setting</p><p class="mt-1 font-bold">{{ $setting->enabled ? 'Enabled' : 'Disabled' }}</p></div>
                <div class="rounded-xl bg-slate-50 p-4"><p class="eyebrow">Effective result</p><p class="mt-1 font-bold">{{ $effective ? 'Pilot available' : 'Pilot unavailable' }}</p></div>
            </div>
            @unless($environmentAllows)<div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"><strong>Environment kill switch is OFF.</strong> Enabling the application setting will not expose the pilot until <code>WALD_IMPORT_AVAILABLE=true</code> is explicitly configured.</div>@endunless
            <div class="mt-4 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-950"><strong>Supervised use only.</strong> Review every import before commit. This pilot processes one selected site at a time and is not automatic RedZebra synchronisation.</div>

            <form method="POST" action="{{ route('office.workspace.settings.wald.update') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $setting->lock_version }}">
                <fieldset><legend class="form-label">Application setting</legend><div class="mt-2 flex flex-wrap gap-4"><label class="flex min-h-11 items-center gap-2"><input type="radio" name="enabled" value="1" required @checked($setting->enabled) class="h-5 w-5"> Enabled</label><label class="flex min-h-11 items-center gap-2"><input type="radio" name="enabled" value="0" required @checked(!$setting->enabled) class="h-5 w-5"> Disabled</label></div></fieldset>
                <div><label for="wald-setting-reason" class="form-label">Reason for change</label><textarea id="wald-setting-reason" name="reason" required maxlength="2000" rows="3" class="form-input mt-1">{{ old('reason') }}</textarea></div>
                @unless($setting->enabled)<label class="flex min-h-12 items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 font-semibold text-amber-950"><input type="checkbox" name="confirmation" value="{{ App\Actions\Administration\UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION }}" class="mt-0.5 h-5 w-5"><span>{{ App\Actions\Administration\UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION }}</span></label>@endunless
                <button type="submit" class="primary-button">Save Wald setting</button>
            </form>
        </section>

        <section class="mt-8" aria-labelledby="wald-audit-title"><p class="eyebrow">Immutable audit</p><h2 id="wald-audit-title" class="section-title">Setting history</h2><div class="admin-card mt-5 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr class="border-b"><th class="p-2">When</th><th class="p-2">Actor</th><th class="p-2">Change</th><th class="p-2">Reason</th></tr></thead><tbody>@forelse($audits as $audit)<tr class="border-b border-slate-100"><td class="p-2">{{ Illuminate\Support\Carbon::parse($audit->created_at)->format('j M Y H:i') }}</td><td class="p-2">{{ $audit->actor_name }}</td><td class="p-2">{{ $audit->old_value ? 'Enabled' : 'Disabled' }} → {{ $audit->new_value ? 'Enabled' : 'Disabled' }}</td><td class="p-2">{{ $audit->reason }}</td></tr>@empty<tr><td class="p-3 text-slate-600" colspan="4">No setting changes recorded.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $audits->links() }}</div></section>
    </div>
</x-layouts.portal>
