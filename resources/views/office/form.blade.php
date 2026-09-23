<x-layouts.portal :title="$title.' | Fenster Customer Portal'" sidebar-label="Menu">
    @include('office.partials.support-styles')
    <div class="admin-workspace support-workspace">
        <a class="admin-back" href="{{ $cancel }}">← {{ $record['name'] ?? 'Back' }}</a>
        <header><p class="eyebrow">{{ $kind === 'site' ? 'Site management' : 'Customer management' }}</p><h1 class="admin-title">{{ $title }}</h1>@if($intent === 'save' && ! $record)<p class="support-intro">{{ $kind === 'site' ? 'Add a site beneath this customer, ready for plots and assigned users.' : 'Add the customer organisation. You can add sites and give users access afterwards.' }}</p>@endif</header>
        <div class="max-w-2xl">
            @if ($customer)
                <p class="mb-5 text-sm text-slate-600 [overflow-wrap:anywhere]">Customer: <strong class="text-slate-900">{{ $customer['name'] }}</strong></p>
            @endif
            @if ($intent !== 'save')
                <div class="admin-notice mb-5">
                    <h2 class="font-bold [overflow-wrap:anywhere]">{{ $record['name'] }}</h2>
                    <p class="mt-2">{{ $intent === 'deactivate' ? ($kind === 'customer' ? 'External users will lose access to this customer’s portal. All sites, users, plots and history will be retained.' : 'External users will lose access to this site. Its plots, assignments and history will be retained.') : ($kind === 'customer' ? 'External users can regain access to active sites assigned to them. Inactive sites and inactive accounts stay inactive.' : 'Assigned users can regain access if their customer and account are also active.') }}</p>
                    <p class="mt-2">This change will be recorded in the activity history.</p>
                </div>
            @elseif ($kind === 'site' && ! $record)
                <p class="support-note mb-5">Create the site now and link its source data later. A source reference is not required. Creating a site does not assign users or import plots.</p>
            @endif
            <noscript><div class="admin-notice">JavaScript is needed to save this form. You can still browse customers and sites. Enable JavaScript and reload to continue.</div></noscript>
            <form x-cloak x-data="officeAdminForm({ redirect: @js($redirect), kind: @js($kind) })" @submit.prevent="save($event)" method="POST" action="{{ $action }}" class="admin-card space-y-5" :aria-busy="saving.toString()">
                @csrf
                @if ($method !== 'POST') @method($method) @endif
                @if ($record)<input type="hidden" name="lock_version" value="{{ $record['lock_version'] }}">@endif
                <div x-ref="summary" tabindex="-1" x-show="message" role="alert" class="admin-error">
                    <h2 class="font-bold" x-text="message"></h2>
                    <ul class="mt-2 list-disc pl-5">
                        <template x-for="(messages, field) in errors" :key="field">
                            <li><a class="underline" :href="'#admin-' + field" x-text="messages[0]"></a></li>
                        </template>
                    </ul>
                    <a x-show="mustReload" class="admin-link-button mt-2" href="{{ url()->current() }}">Reload current details</a>
                    <p x-show="uncertain" class="mt-2">Check the record before trying again; the save may have completed.</p>
                    <a x-show="uncertain" class="admin-link-button" href="{{ $cancel }}">Check current records</a>
                </div>
                <fieldset :disabled="saving || uncertain || mustReload" class="min-w-0 space-y-5">
                    <legend class="form-label mb-4">{{ $intent === 'save' ? ($kind === 'site' ? 'Site details' : 'Customer details') : 'Confirm this change' }}</legend>
                    @if ($intent === 'save')
                        <div>
                            <label class="form-label" for="admin-name">{{ $kind === 'site' ? 'Site name' : 'Customer name' }} <span class="font-normal">(required)</span></label>
                            <input class="form-input" id="admin-name" name="name" type="text" required maxlength="255" value="{{ $record['name'] ?? '' }}" autocomplete="off" :aria-invalid="Boolean(errors.name).toString()" aria-describedby="admin-name-error">
                            <p id="admin-name-error" class="form-error" x-show="errors.name" x-text="errors.name?.[0]"></p>
                        </div>
                        @if ($kind === 'site')
                            <div>
                                <label class="form-label" for="admin-location">Location <span class="font-normal">(optional)</span></label>
                                <input class="form-input" id="admin-location" name="location" type="text" maxlength="255" value="{{ $record['location'] ?? '' }}" :aria-invalid="Boolean(errors.location).toString()" aria-describedby="admin-location-error">
                                <p id="admin-location-error" class="form-error" x-show="errors.location" x-text="errors.location?.[0]"></p>
                            </div>
                            <p class="text-sm text-slate-600">Source linking is managed separately. Editing these details will not change a source binding.</p>
                        @endif
                    @else
                        <div>
                            <label class="form-label" for="admin-reason">Reason <span class="font-normal">(required)</span></label>
                            <textarea class="form-input" id="admin-reason" name="reason" rows="3" required maxlength="2000" :aria-invalid="Boolean(errors.reason).toString()" aria-describedby="admin-reason-help admin-reason-error"></textarea>
                            <p id="admin-reason-help" class="mt-2 text-sm text-slate-600">Briefly explain this change for other Office Staff. Maximum 2,000 characters.</p>
                            <p id="admin-reason-error" class="form-error" x-show="errors.reason" x-text="errors.reason?.[0]"></p>
                        </div>
                    @endif
                    <div class="admin-actions border-t border-slate-200 pt-5">
                        <button class="{{ $intent === 'deactivate' ? 'reject-button' : 'primary-button' }}" type="submit"><span x-show="!saving">{{ $intent === 'save' ? ($record ? 'Save Changes' : ($kind === 'site' ? 'Create Site' : 'Create Customer')) : 'Confirm '.ucfirst($intent) }}</span><span x-show="saving" role="status">Saving…</span></button>
                        <a class="secondary-button" href="{{ $cancel }}">Cancel</a>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
</x-layouts.portal>
