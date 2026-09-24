<?php

namespace App\Http\Controllers;

use App\SourceImport\Integration\CustomerCodeReviewWorkflow;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\WaldPilotAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OfficeCustomerCodeReviewController extends Controller
{
    public function show(Request $request, string $upload, CustomerCodeReviewWorkflow $workflow): View
    {
        $this->enabled();
        $data = $request->validate(['source' => ['sometimes', 'regex:/^[a-f0-9]{64}$/D']]);
        $overview = $workflow->overview($request->user(), $upload);
        $current = isset($data['source'])
            ? collect($overview['manual'])->firstWhere('hash', $data['source'])
            : ($overview['pending'][0] ?? null);
        if (isset($data['source']) && ! $current) {
            abort(404);
        }
        $rows = $current
            ? DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $overview['import']['id'])
                ->where('source_identity_hash', $current['hash'])->orderBy('row_number')->get()
            : collect();
        $customers = DB::table('customer_organisations')->where('is_active', true)
            ->orderBy('name')->get(['id', 'uuid', 'name']);
        $sites = DB::table('sites')->where('is_active', true)->orderBy('name')
            ->get(['uuid', 'name', 'customer_organisation_id']);

        return view('office.pilot-import.customer-code-review', compact('overview', 'current', 'rows', 'customers', 'sites'));
    }

    public function confirm(Request $request, string $upload, string $sourceHash,
        CustomerCodeReviewWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'customer_uuid' => ['required', 'uuid'],
            'site_uuid' => ['required', 'uuid'],
            'excluded_rows_csv' => ['present', 'nullable', 'string', 'max:30000'],
            'source_manifest_hash' => ['required', 'size:64'],
            'expected_epoch' => ['required', 'integer', 'min:0'],
            'confirmation' => ['required', Rule::in(['CONFIRM CUSTOMER CODE ROWS'])],
            'command_uuid' => ['required', 'uuid'],
            'confirm_binding' => ['sometimes', 'accepted'],
        ]);
        try {
            $workflow->confirm($request->user(), $upload, $sourceHash,
                $data['customer_uuid'], $data['site_uuid'], $data['excluded_rows_csv'] ?? '',
                $data['source_manifest_hash'], (int) $data['expected_epoch'], $data['command_uuid'],
                false, isset($data['confirm_binding']));
        } catch (ImportConflict $exception) {
            return back()->withErrors(['review' => 'The review could not be saved: '.str_replace('_', ' ', $exception->getMessage()).'.']);
        }

        return redirect()->route('office.workspace.pilot-import.customer-codes.review', $upload)
            ->with('status', 'CustomerCode reviewed. The next group is ready. No plots have been applied.');
    }

    public function defer(Request $request, string $upload, string $sourceHash,
        CustomerCodeReviewWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'source_manifest_hash' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'expected_epoch' => ['required', 'integer', 'min:0'],
            'confirmation' => ['required', Rule::in(['SEND CUSTOMER CODE TO UNKNOWN'])],
            'command_uuid' => ['required', 'uuid'],
        ]);
        try {
            $workflow->confirm($request->user(), $upload, $sourceHash, null, null, '',
                $data['source_manifest_hash'], (int) $data['expected_epoch'], $data['command_uuid'], true);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['review' => 'The group could not be deferred: '.str_replace('_', ' ', $exception->getMessage()).'.']);
        }

        return redirect()->route('office.workspace.pilot-import.customer-codes.review', $upload)
            ->with('status', 'All rows in this CustomerCode moved to Unknown review. The next group is ready.');
    }

    private function enabled(): void
    {
        abort_unless((new WaldPilotAvailability)->enabled(), 404);
    }
}
