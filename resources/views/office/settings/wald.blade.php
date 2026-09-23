@php
    $selectedEnabled = (string) old('enabled', $setting->enabled ? '1' : '0');
@endphp
<x-layouts.portal title="Settings | Fenster Customer Portal" sidebar-label="Menu">
    <div class="admin-workspace min-w-0" data-office-settings-workspace>
        <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="eyebrow">Office administration</p><h1 class="admin-title">Settings</h1><p class="admin-intro">Manage the controls available to Fenster Office Staff.</p></div>
            <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">Office only</span>
        </header>

        @if (session('status'))<div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 font-semibold text-emerald-950" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-rose-950" role="alert"><h2 class="font-bold">The setting was not changed.</h2><ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="wald-overview-title">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0"><p class="eyebrow">Imports</p><h2 id="wald-overview-title" class="section-title mt-1">Wald Import Pilot</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Control access to the supervised spreadsheet import workspace.</p></div>
                @if ($effective)<a class="secondary-button" href="{{ route('office.workspace.imports') }}">Open imports</a>@endif
            </div>
            <dl class="mt-5 grid gap-3 lg:grid-cols-3">
                <div @class(['rounded-xl border p-4', 'border-emerald-200 bg-emerald-50' => $effective, 'border-amber-200 bg-amber-50' => ! $effective])><dt class="text-sm font-medium text-slate-700">Current availability</dt><dd class="mt-1 text-lg font-bold text-slate-950">{{ $effective ? 'Pilot available' : 'Pilot unavailable' }}</dd><dd class="mt-2 text-sm leading-6 text-slate-700">{{ $effective ? 'Authorised Office Staff can open the import workspace.' : 'Import access needs both the saved setting and deployment access to be on.' }}</dd></div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><dt class="text-sm font-medium text-slate-600">Saved application setting</dt><dd class="mt-1 text-lg font-bold text-slate-950">{{ $setting->enabled ? 'Enabled' : 'Disabled' }}</dd><dd class="mt-2 text-sm leading-6 text-slate-600">Change this below. Your selection takes effect when saved.</dd></div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><dt class="text-sm font-medium text-slate-600">Deployment access</dt><dd class="mt-1 text-lg font-bold text-slate-950">{{ $environmentAllows ? 'Allows Wald' : 'Emergency OFF' }}</dd><dd class="mt-2 text-sm leading-6 text-slate-600">Managed by the deployment team. This page cannot override it.</dd></div>
            </dl>
            @unless ($environmentAllows)<p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950"><strong>Imports are paused at deployment level.</strong> You can save the application setting, but the pilot will remain unavailable until the deployment team restores access.</p>@endunless
        </section>

        <div class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="wald-control-title">
                <h2 id="wald-control-title" class="section-title">Change import access</h2><p class="mt-2 text-sm leading-6 text-slate-600">Every change records who made it, when it changed and the reason.</p>
                <form method="POST" action="{{ route('office.workspace.settings.wald.update') }}" class="mt-5 space-y-5" x-data="{ selectedEnabled: @js($selectedEnabled) }">
                    @csrf @method('PUT')
                    <input type="hidden" name="lock_version" value="{{ $setting->lock_version }}">
                    <fieldset aria-describedby="wald-choice-help{{ $errors->has('enabled') ? ' wald-enabled-error' : '' }}"><legend class="form-label">Application setting</legend><p id="wald-choice-help" class="mt-1 text-sm leading-6 text-slate-600">Choose whether Office Staff may use the pilot when deployment access allows it.</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50"><input type="radio" name="enabled" value="1" required x-model="selectedEnabled" @checked($selectedEnabled === '1') class="mt-0.5 h-5 w-5 shrink-0 focus:ring-2 focus:ring-sky-600 focus:ring-offset-2"><span class="text-sm leading-6"><strong class="block text-slate-950">Enabled</strong><span class="text-slate-600">Allow supervised Office imports.</span></span></label>
                            <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50"><input type="radio" name="enabled" value="0" required x-model="selectedEnabled" @checked($selectedEnabled === '0') class="mt-0.5 h-5 w-5 shrink-0 focus:ring-2 focus:ring-sky-600 focus:ring-offset-2"><span class="text-sm leading-6"><strong class="block text-slate-950">Disabled</strong><span class="text-slate-600">Stop access; keep existing history.</span></span></label>
                        </div>
                        @error('enabled')<p id="wald-enabled-error" class="mt-2 text-sm font-semibold text-rose-800">{{ $message }}</p>@enderror
                    </fieldset>
                    <div><label for="wald-setting-reason" class="form-label">Reason for change <span class="font-normal">(required)</span></label><p id="wald-reason-help" class="mt-1 text-sm leading-6 text-slate-600">Explain why this setting is changing. Up to 2,000 characters; visible to Office Staff in the history below.</p><textarea id="wald-setting-reason" name="reason" required maxlength="2000" rows="4" class="form-input mt-2 min-w-0" aria-describedby="wald-reason-help{{ $errors->has('reason') ? ' wald-reason-error' : '' }}" aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}">{{ old('reason') }}</textarea>@error('reason')<p id="wald-reason-error" class="mt-2 text-sm font-semibold text-rose-800">{{ $message }}</p>@enderror</div>
                    @unless ($setting->enabled)
                        <div x-show="selectedEnabled === '1'"><label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-950"><input type="checkbox" name="confirmation" value="{{ App\Actions\Administration\UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION }}" @checked(old('confirmation') === App\Actions\Administration\UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION) class="mt-1 h-5 w-5 shrink-0 focus:ring-2 focus:ring-sky-600 focus:ring-offset-2" @if($errors->has('confirmation')) aria-describedby="wald-confirmation-error" aria-invalid="true" @endif><span>{{ App\Actions\Administration\UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION }}</span></label>@error('confirmation')<p id="wald-confirmation-error" class="mt-2 text-sm font-semibold text-rose-800">{{ $message }}</p>@enderror</div>
                    @endunless
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center"><button type="submit" class="primary-button">Save Wald setting</button><a class="secondary-button" href="{{ route('office.workspace.settings.wald') }}">Cancel changes</a></div>
                </form>
            </section>
            <aside class="min-w-0 rounded-xl border border-sky-200 bg-sky-50 p-5 sm:p-6" aria-labelledby="wald-supervision-title">
                <h2 id="wald-supervision-title" class="section-title text-sky-950">Supervised use only</h2><ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-6 text-sky-950"><li>Only authorised Fenster Office Staff can import. Customer users never receive access.</li><li>Review every import before committing it.</li><li>One selected site is processed at a time.</li><li>This pilot does not automatically synchronise RedZebra.</li></ul>
            </aside>
        </div>

        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="wald-audit-title">
            <h2 id="wald-audit-title" class="section-title">Setting history</h2><p class="mt-2 text-sm leading-6 text-slate-600">Recorded changes, newest first. History cannot be edited. Times are shown in UTC.</p>
            <ol class="mt-5 space-y-3">
                @forelse ($audits as $audit)
                    <li class="min-w-0 rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="font-bold text-slate-950"><span class="sr-only">Changed from </span>{{ $audit->old_value ? 'Enabled' : 'Disabled' }} <span aria-hidden="true">→</span><span class="sr-only"> to </span> {{ $audit->new_value ? 'Enabled' : 'Disabled' }}</h3><time class="text-sm text-slate-600" datetime="{{ Illuminate\Support\Carbon::parse($audit->created_at, 'UTC')->toIso8601String() }}">{{ Illuminate\Support\Carbon::parse($audit->created_at, 'UTC')->format('j M Y, H:i') }} UTC</time></div>
                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"><div class="min-w-0"><dt class="font-medium text-slate-500">Changed by</dt><dd class="mt-1 font-semibold text-slate-800 [overflow-wrap:anywhere]">{{ $audit->actor_name }}</dd></div><div class="min-w-0"><dt class="font-medium text-slate-500">Reason</dt><dd class="mt-1 whitespace-pre-line leading-6 text-slate-700 [overflow-wrap:anywhere]">{{ $audit->reason }}</dd></div></dl>
                    </li>
                @empty
                    <li class="rounded-lg bg-slate-50 p-5"><h3 class="font-semibold text-slate-900">No setting changes recorded.</h3><p class="mt-1 text-sm leading-6 text-slate-600">Saved changes will appear here with their reason and the person who made them.</p></li>
                @endforelse
            </ol>
            @if ($audits->hasPages())<div class="mt-5">{{ $audits->links() }}</div>@endif
        </section>
    </div>
</x-layouts.portal>
