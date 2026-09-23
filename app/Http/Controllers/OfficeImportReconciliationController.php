<?php

namespace App\Http\Controllers;

use App\Enums\CallOffServiceType;
use App\Services\Reconciliation\MasterReconciliationWorkspace;
use App\SourceImport\Integration\PilotImportPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OfficeImportReconciliationController extends Controller
{
    public function show(Request $request, string $upload, MasterReconciliationWorkspace $workspace): View
    {
        (new PilotImportPolicy)->authorize($request->user());
        $filters = $request->validate(['status' => ['sometimes', Rule::in(['all', 'pending', 'completed'])],
            'site' => ['nullable', 'uuid'], 'service' => ['nullable', Rule::enum(CallOffServiceType::class)],
            'q' => ['nullable', 'string', 'max:80'], 'amendment' => ['nullable', 'uuid'],
            'amendments_page' => ['sometimes', 'integer', 'min:1'], 'sites_page' => ['sometimes', 'integer', 'min:1'],
            'history_page' => ['sometimes', 'integer', 'min:1']]);

        return view('office.reconciliation.show', $workspace->read($request->user(), $upload, $filters));
    }
}
