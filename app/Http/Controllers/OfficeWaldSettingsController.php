<?php

namespace App\Http\Controllers;

use App\Actions\Administration\UpdateWaldPilotSettingAction;
use App\Policies\OfficeAdministrationPolicy;
use App\SourceImport\Integration\WaldPilotAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OfficeWaldSettingsController extends Controller
{
    public function index(Request $request, WaldPilotAvailability $availability): View
    {
        (new OfficeAdministrationPolicy)->authorize($request->user(), 'view');

        return view('office.settings.wald', [
            'setting' => DB::table('wald_pilot_settings')->where('key', WaldPilotAvailability::KEY)->firstOrFail(),
            'audits' => DB::table('wald_pilot_setting_events')->orderByDesc('id')->paginate(20),
            'environmentAllows' => $availability->environmentAllows(),
            'effective' => $availability->enabled(),
        ]);
    }

    public function update(Request $request, UpdateWaldPilotSettingAction $action): RedirectResponse
    {
        (new OfficeAdministrationPolicy)->authorize($request->user(), 'settings_update');
        $data = $request->validate([
            'enabled' => ['required', Rule::in(['0', '1'])],
            'lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'confirmation' => ['nullable', 'string'],
        ]);
        $enabled = $data['enabled'] === '1';
        $action->handle($request->user(), $enabled, (int) $data['lock_version'], $data['reason'], $data['confirmation'] ?? null);

        return redirect()->route('office.workspace.settings.wald')->with('status', $enabled
            ? 'Wald Import Pilot enabled at the application layer.'
            : 'Wald Import Pilot disabled immediately. Existing history was preserved.');
    }
}
