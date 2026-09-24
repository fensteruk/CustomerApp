<?php

namespace App\Http\Controllers;

use App\SourceImport\Integration\CustomerCodeReviewWorkflow;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\MasterSourceResolver;
use App\SourceImport\Integration\UnknownRowsWorkflow;
use App\SourceImport\Integration\WaldPilotAvailability;
use App\SourceImport\Knowledge\Canonical;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class OfficeUnknownRowsController extends Controller
{
    public function show(Request $request, string $upload, CustomerCodeReviewWorkflow $workflow): View|RedirectResponse
    {
        $this->enabled();
        $overview = $workflow->overview($request->user(), $upload);
        if ($overview['pending'] !== []) {
            return redirect()->route('office.workspace.pilot-import.customer-codes.review', $upload);
        }
        $query = DB::table('wald_pilot_review_rows')->where('pilot_upload_id', $overview['import']['id']);
        $rows = (clone $query)->whereIn('disposition', ['UNKNOWN', 'REVIEWED_MISSING_CODE'])
            ->orderBy('row_number')->paginate(25, ['*'], 'unknown_page');
        $counts = [
            'unresolved' => (clone $query)->whereIn('disposition', ['UNKNOWN', 'REVIEWED_MISSING_CODE'])->count(),
            'excluded' => (clone $query)->where('disposition', 'EXCLUDED')->count(),
            'confirmed' => (clone $query)->where('disposition', 'CONFIRMED')->count(),
            'resolved_manually' => 0,
        ];
        $createdCustomers = 0;
        $createdSites = 0;
        foreach (DB::table('wald_pilot_events')->where('pilot_upload_id', $overview['import']['id'])
            ->whereIn('action', ['pilot_unknown_row_reviewed', 'pilot_unknown_code_bulk_reviewed',
                'pilot_hierarchy_creation_approved'])->get(['action', 'payload']) as $event) {
            $payload = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
            if ($event->action === 'pilot_unknown_row_reviewed') {
                $counts['resolved_manually'] += ($payload['disposition'] ?? null) === 'CONFIRMED' ? 1 : 0;
            } elseif ($event->action === 'pilot_unknown_code_bulk_reviewed') {
                $counts['resolved_manually'] += (int) ($payload['resolved_rows'] ?? 0);
            } else {
                $createdCustomers += ! empty($payload['customer_created']) ? 1 : 0;
                $createdSites += ! empty($payload['site_created']) ? 1 : 0;
            }
        }
        $unknownCodes = (clone $query)->where('disposition', 'UNKNOWN')
            ->whereNotNull('customer_code')->groupBy('customer_code')
            ->orderBy('customer_code')->get(['customer_code', DB::raw('COUNT(*) as row_count')]);
        $customers = DB::table('customer_organisations')->where('is_active', true)
            ->orderBy('name')->get(['id', 'uuid', 'name']);
        $sites = DB::table('sites')->where('is_active', true)->orderBy('name')
            ->get(['uuid', 'name', 'customer_organisation_id']);
        $recommendations = [];
        $codeRows = (clone $query)->where('disposition', 'UNKNOWN')->whereNotNull('customer_code')
            ->get(['customer_code', 'row_number', 'raw_plot_ref', 'parsed_customer', 'parsed_site']);
        $rowsByCode = $codeRows->groupBy('customer_code')->map(fn ($group) => $group->map(fn (object $row): array => ['row' => (int) $row->row_number, 'raw' => $row->raw_plot_ref])->all())->all();
        $namespace = DB::table('wald_pilot_uploads as uploads')
            ->join('wald_import_streams as streams', 'streams.id', '=', 'uploads.stream_id')
            ->where('uploads.id', $overview['import']['id'])->value('streams.source_namespace');
        $sourcesByCode = collect($overview['import']['sources'])->keyBy('customer_code');
        $identityHashes = $unknownCodes->mapWithKeys(function (object $item) use ($sourcesByCode, $namespace): array {
            $source = $sourcesByCode->get($item->customer_code);

            return $source ? [$item->customer_code => Canonical::hash([$namespace, $source['kind'], $source['identity']])] : [];
        });
        $bindings = DB::table('wald_source_bindings as bindings')
            ->join('wald_binding_versions as versions', function ($join): void {
                $join->on('versions.binding_id', '=', 'bindings.id')
                    ->on('versions.version', '=', 'bindings.active_version');
            })
            ->join('sites as bound_sites', 'bound_sites.id', '=', 'versions.site_id')
            ->join('customer_organisations as bound_customers', 'bound_customers.id', '=', 'versions.customer_organisation_id')
            ->whereIn('bindings.identity_hash', $identityHashes->values()->all())
            ->get(['bindings.identity_hash', 'bound_sites.uuid as site_uuid', 'bound_sites.name as site_name',
                'bound_customers.name as customer_name'])->keyBy('identity_hash');
        foreach ($unknownCodes as $item) {
            $evidence = $codeRows->where('customer_code', $item->customer_code)
                ->filter(fn (object $row): bool => $row->parsed_customer !== null && $row->parsed_site !== null);
            $first = $evidence->first();
            $consistent = $first && ! $evidence->contains(fn (object $row): bool => ! MasterSourceResolver::sameName($row->parsed_customer, $first->parsed_customer)
                || ! MasterSourceResolver::sameName($row->parsed_site, $first->parsed_site));
            $customerMatches = $consistent ? $customers->filter(fn (object $candidate): bool => MasterSourceResolver::sameName($candidate->name, $first->parsed_customer)) : collect();
            $customer = $customerMatches->count() === 1 ? $customerMatches->first() : null;
            $siteMatches = $customer ? $sites->filter(fn (object $candidate): bool => (int) $candidate->customer_organisation_id === (int) $customer->id
                && MasterSourceResolver::sameName($candidate->name, $first->parsed_site)) : collect();
            $site = $siteMatches->count() === 1 ? $siteMatches->first() : null;
            $binding = $bindings->get($identityHashes->get($item->customer_code));
            $recommendations[$item->customer_code] = [
                'customer' => $site ? $customer->uuid : '', 'site' => $site?->uuid ?? '',
                'source_customer' => $first?->parsed_customer ?? '', 'source_site' => $first?->parsed_site ?? '',
                'binding_customer' => $binding?->customer_name ?? '',
                'binding_site' => $binding?->site_name ?? '',
                'binding_site_uuid' => $binding?->site_uuid ?? '',
            ];
        }

        return view('office.pilot-import.unknown-rows', compact('overview', 'rows', 'counts', 'unknownCodes', 'customers', 'sites',
            'createdCustomers', 'createdSites', 'recommendations', 'rowsByCode'));
    }

    public function resolveCode(Request $request, string $upload, UnknownRowsWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'customer_code' => ['required', 'string', 'max:100'],
            'customer_uuid' => ['required', 'uuid'], 'site_uuid' => ['required', 'uuid'],
            'confirmation' => ['required', 'in:REVIEW MATCHING CUSTOMER CODE ROWS'],
            'source_manifest_hash' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'expected_epoch' => ['required', 'integer', 'min:0'],
            'command_uuid' => ['required', 'uuid'],
            'confirm_binding' => ['sometimes', 'accepted'],
            'excluded_rows_csv' => ['nullable', 'string', 'max:30000'],
        ]);
        try {
            $result = $workflow->resolveCode($request->user(), $upload, $data['customer_code'],
                $data['customer_uuid'], $data['site_uuid'], $data['source_manifest_hash'],
                (int) $data['expected_epoch'], $data['command_uuid'], isset($data['confirm_binding']),
                $data['excluded_rows_csv'] ?? '');
        } catch (ImportConflict $exception) {
            return back()->withErrors(['unknown' => 'The matching rows could not be reviewed: '.str_replace('_', ' ', $exception->getMessage()).'.']);
        }

        return redirect()->route('office.workspace.pilot-import.unknown.show', $upload)
            ->with('status', $result['resolved'].' rows received exact plots; '.$result['unknown'].' still need individual review.');
    }

    public function resolve(Request $request, string $upload, int $rowNumber, UnknownRowsWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate([
            'customer_uuid' => ['required', 'uuid'], 'site_uuid' => ['required', 'uuid'],
            'plot' => ['nullable', 'string', 'max:200'], 'command_uuid' => ['required', 'uuid'],
            'source_manifest_hash' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'expected_epoch' => ['required', 'integer', 'min:0'],
        ]);
        try {
            $workflow->resolve($request->user(), $upload, $rowNumber, $data['customer_uuid'],
                $data['site_uuid'], $data['plot'] ?? null, $data['source_manifest_hash'],
                (int) $data['expected_epoch'], $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['unknown' => 'The row could not be resolved: '.str_replace('_', ' ', $exception->getMessage()).'.']);
        }

        return redirect()->route('office.workspace.pilot-import.unknown.show', $upload)
            ->with('status', 'Row decision saved. Any confirmed plot still requires site preview and explicit Apply.');
    }

    public function exclude(Request $request, string $upload, int $rowNumber, UnknownRowsWorkflow $workflow): RedirectResponse
    {
        $this->enabled();
        $data = $request->validate(['reason' => ['required', 'string', 'max:100'],
            'source_manifest_hash' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'expected_epoch' => ['required', 'integer', 'min:0'],
            'command_uuid' => ['required', 'uuid']]);
        try {
            $workflow->exclude($request->user(), $upload, $rowNumber, $data['reason'],
                $data['source_manifest_hash'], (int) $data['expected_epoch'], $data['command_uuid']);
        } catch (ImportConflict $exception) {
            return back()->withErrors(['unknown' => 'The row could not be excluded: '.str_replace('_', ' ', $exception->getMessage()).'.']);
        }

        return redirect()->route('office.workspace.pilot-import.unknown.show', $upload)
            ->with('status', 'Row excluded from this upload and recorded in the Office audit.');
    }

    private function enabled(): void
    {
        abort_unless((new WaldPilotAvailability)->enabled(), 404);
    }
}
