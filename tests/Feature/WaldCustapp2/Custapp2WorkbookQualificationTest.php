<?php

use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use App\SourceImport\Integration\ImportAnalysis;
use App\SourceImport\Integration\ImportConflict;
use App\SourceImport\Integration\ImportReview;
use App\SourceImport\Integration\PilotImportWorkflow;
use App\SourceImport\Integration\SourceBindingService;
use App\SourceImport\Knowledge\Actions\AnswerClarification;
use App\SourceImport\Knowledge\KnowledgeQueries;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\Models\KnowledgeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function custapp2PrivatePath(): string
{
    return (string) getenv('CUSTAPP2_WORKBOOK');
}

function custapp2Office(): User
{
    return User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
    ]);
}

function custapp2Bind(User $office, array $source, Site $site): void
{
    $scope = new KnowledgeScope($site->customer_organisation_id, $site->id, 'redzebra', 'call-offs');
    $bindings = new SourceBindingService;
    $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Explicit local CUSTAPP2 qualification binding.', (string) Str::uuid());
    $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Reviewed local CUSTAPP2 qualification binding.', (string) Str::uuid());
}

function custapp2Analyse(User $office, KnowledgeScope $scope, object $run): void
{
    try {
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    } catch (ImportConflict $exception) {
        if ($exception->getMessage() !== 'structural_clarification_required') {
            throw $exception;
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $context = KnowledgeContext::query()->findOrFail($run->context_id);
        foreach ((new KnowledgeQueries)->questions($office, $scope, $context->uuid) as $question) {
            if ($question['state'] !== 'ANSWERED' && $question['evidence']['type'] === 'STRUCTURAL' && count($question['evidence']['candidates']) === 1) {
                (new AnswerClarification)->handle(
                    $office,
                    $scope,
                    $context->uuid,
                    $question['uuid'],
                    $question['sequence'],
                    $question['evidence']['candidates'][0]['id'],
                    'Reviewed deterministic CUSTAPP2 composite header.',
                    (string) Str::uuid(),
                );
            }
        }
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
    }
}

it('qualifies the private CUSTAPP2 workbook through nine explicit local site commits', function (): void {
    $path = custapp2PrivatePath();
    if ($path === '' || ! is_file($path)) {
        $this->markTestSkipped('Set CUSTAPP2_WORKBOOK to the approved private workbook path.');
    }

    expect(hash_file('sha256', $path))->toBe('a9a5f2214d3b687af9043bb0f5232d5d105bb0e9c677c1e82e0b1e0e82b285df');
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);

    $office = custapp2Office();
    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload(
        $office,
        new UploadedFile($path, 'private-custapp2.xlsx', null, null, true),
        new ExportOrder('2026-09-15', 'MORNING'),
        ExportOrder::CONFIRMATION,
        (string) Str::uuid(),
    );
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    try {
        expect($pilot['mode'])->toBe('PILOT_SINGLE_SITE_SELECTION')
            ->and($pilot['state'])->toBe('READY')
            ->and($pilot['manifest']['logical_table'])->toBe('C2:E2+I2:AJ2')
            ->and($pilot['sources'])->toHaveCount(9)
            ->and($pilot['manifest']['record_count'])->toBe(33)
            ->and($pilot['manifest']['included_count'])->toBe(33)
            ->and($pilot['manifest']['excluded_count'])->toBe(0);

        $receipts = [];
        $blockedSelections = 0;
        foreach ($pilot['sources'] as $index => $source) {
            $customer = CustomerOrganisation::factory()->create(['name' => 'CUSTAPP2 Qualification Customer '.($index + 1).' '.Str::uuid()]);
            $site = Site::factory()->create([
                'customer_organisation_id' => $customer->id,
                'name' => 'CUSTAPP2 Qualification Site '.($index + 1),
                'is_active' => true,
            ]);
            custapp2Bind($office, $source, $site);
            $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
            $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
            custapp2Analyse($office, $selected['scope'], $run);
            $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
            $review = new ImportReview;
            $preview = $review->preview($office, $selected['scope'], $run->uuid, (int) $run->epoch, (string) Str::uuid());
            if ($preview['blockers'] !== []) {
                expect($preview['blockers'])->toBe(['BLOCKED_STAGED_RECORDS']);
                $blockedSelections++;

                continue;
            }
            $review->approve($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
            $receipt = $review->commit($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());
            expect($review->commit($office, $selected['scope'], $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid()))->toBe($receipt);
            $receipts[] = $receipt;
        }

        expect($blockedSelections)->toBe(1)
            ->and(DB::table('wald_import_receipts')->count())->toBe(8)
            ->and(DB::table('wald_pilot_selections')->where('state', 'COMMITTED')->count())->toBe(8)
            ->and(DB::table('projected_plots')->count())->toBe(20)
            ->and(DB::table('projected_plot_products')->count())->toBe(200)
            ->and(DB::table('wald_source_rows')->count())->toBe(26)
            ->and(DB::table('wald_source_row_observations')->count())->toBe(26)
            ->and(DB::table('wald_source_visits')->count())->toBe(13)
            ->and(DB::table('wald_visit_observations')->count())->toBe(13)
            ->and(DB::table('wald_source_visits')->where('call_type', 'PC1')->count())->toBe(5)
            ->and(DB::table('wald_source_visits')->where('call_type', 'CC1')->count())->toBe(7)
            ->and(DB::table('wald_source_visits')->where('call_type', 'CM1')->count())->toBe(1)
            ->and(DB::table('call_off_requests')->count())->toBe(0)
            ->and(DB::table('portal_notifications')->count())->toBe(0)
            ->and(collect($receipts)->sum(fn (array $receipt): int => $receipt['counts']['seen']))->toBe(26);
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});
