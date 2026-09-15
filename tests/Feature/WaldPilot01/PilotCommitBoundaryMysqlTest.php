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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Wald05BackendFixtures;

it('commits one pilot site through the production-like HTTP boundary on MySQL', function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable WALD pilot MySQL 8.4 QA.');
    }
    if (! app()->environment('testing')
        || config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_wald05_')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')
        || DB::transactionLevel() !== 0) {
        throw new RuntimeException('disposable_wald_pilot_mysql84_required');
    }

    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);

    $office = User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
    ]);
    $customer = CustomerOrganisation::factory()->create(['name' => 'Synthetic Pilot Boundary Customer '.Str::uuid()]);
    $site = Site::factory()->create([
        'customer_organisation_id' => $customer->id,
        'name' => 'Synthetic Pilot Boundary Site',
        'is_active' => true,
    ]);

    $workflow = new PilotImportWorkflow;
    $pilot = $workflow->upload(
        $office,
        Wald05BackendFixtures::workbook(
            overrides: [0 => ['CustomerNo' => 'SYNTHETIC-CUSTOMER-MYSQL']],
            headers: ['CustomerNo', 'Call No.', 'Site Name', 'Plot', 'Call Type', 'complete', 'VS', 'BF'],
            count: 1,
        ),
        new ExportOrder('2099-01-01', 'MORNING'),
        ExportOrder::CONFIRMATION,
        (string) Str::uuid(),
    );
    if ($pilot['state'] === 'NEEDS_CLARIFICATION') {
        $pilot = $workflow->confirmStructure($office, $pilot['upload'], 'CONFIRM DETECTED HEADER AND SITE LIST', (string) Str::uuid());
    }
    $upload = DB::table('wald_pilot_uploads')->where('uuid', $pilot['upload'])->firstOrFail();

    try {
        $source = $pilot['sources'][0];
        $scope = new KnowledgeScope($customer->id, $site->id, 'redzebra', 'call-offs');
        $bindings = new SourceBindingService;
        $draft = $bindings->draft($office, $scope, $source['kind'], $source['identity'], 'Synthetic pilot boundary binding.', (string) Str::uuid());
        $bindings->activate($office, $scope, $draft['binding'], $draft['version'], $draft['definition_hash'], $draft['epoch'], 'Synthetic pilot boundary activation.', (string) Str::uuid());

        $selected = $workflow->select($office, $pilot['upload'], $source['hash'], $site->uuid, (string) Str::uuid());
        $run = DB::table('wald_import_runs')->where('uuid', $selected['run'])->firstOrFail();
        try {
            (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        } catch (ImportConflict $exception) {
            if ($exception->getMessage() !== 'structural_clarification_required') {
                throw $exception;
            }
            $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
            $context = KnowledgeContext::query()->findOrFail($run->context_id);
            foreach ((new KnowledgeQueries)->questions($office, $scope, $context->uuid) as $question) {
                if ($question['state'] !== 'ANSWERED'
                    && $question['evidence']['type'] === 'STRUCTURAL'
                    && count($question['evidence']['candidates']) === 1) {
                    (new AnswerClarification)->handle(
                        $office,
                        $scope,
                        $context->uuid,
                        $question['uuid'],
                        $question['sequence'],
                        $question['evidence']['candidates'][0]['id'],
                        'Reviewed synthetic pilot header.',
                        (string) Str::uuid(),
                    );
                }
            }
            $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
            (new ImportAnalysis)->analyse($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        }

        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $preview = (new ImportReview)->preview($office, $scope, $run->uuid, (int) $run->epoch, (string) Str::uuid());
        expect($preview['blockers'])->toBe([]);
        (new ImportReview)->approve($office, $scope, $run->uuid, $preview['preview'], $preview['hash'], (string) Str::uuid());

        $command = (string) Str::uuid();
        $token = (string) Str::uuid();
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');
        try {
            expect(app()->runningUnitTests())->toBeFalse()
                ->and(DB::transactionLevel())->toBe(0);
            $response = $this->withSession(['_token' => $token])->actingAs($office)->post(
                route('office.workspace.pilot-import.selections.commit', [$pilot['upload'], $selected['selection']]),
                [
                    '_token' => $token,
                    'preview' => $preview['preview'],
                    'hash' => $preview['hash'],
                    'confirmation' => 'COMMIT THIS ONE SITE',
                    'command_uuid' => $command,
                ],
            );
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $response->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'One selected site committed atomically. No other source site was changed.');
        $run = DB::table('wald_import_runs')->where('id', $run->id)->firstOrFail();
        $attempt = DB::table('wald_commit_attempts')->where('command_uuid', $command)->firstOrFail();
        expect($run->state)->toBe('COMMITTED')
            ->and(DB::table('wald_import_receipts')->where('run_id', $run->id)->count())->toBe(1)
            ->and(DB::table('wald_commit_attempts')->where('command_uuid', $command)->count())->toBe(1)
            ->and(DB::table('wald_commit_attempt_outcomes')->where('attempt_id', $attempt->id)->value('outcome'))->toBe('SUCCEEDED')
            ->and(DB::table('wald_pilot_selections')->where('uuid', $selected['selection'])->value('state'))->toBe('COMMITTED');
    } finally {
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($upload->storage_key);
    }
});
