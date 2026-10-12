<?php

use App\Models\PortalRole;
use App\Models\User;
use App\SourceImport\Integration\WaldDryRunDiagnostics;
use App\Wald\Services\AnalysisProblem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('keeps dry run diagnostics off by default', function (): void {
    Log::spy();
    expect(config('wald_import.dry_run_diagnostics'))->toBeFalse();
    (new WaldDryRunDiagnostics)->stopped(new AnalysisProblem('invalid_csv'), hrtime(true));
    Log::shouldNotHaveReceived('warning');
});

it('records only allowlisted codes limits and runtime measurements', function (): void {
    config(['wald_import.dry_run_diagnostics' => true]);
    Log::spy();
    (new WaldDryRunDiagnostics)->stopped(new AnalysisProblem('resource_limit_exceeded', [
        'limit' => 'rows', 'maximum' => 20001, 'source' => 'PRIVATE-SOURCE', 'password' => 'PRIVATE-PASSWORD',
    ]), hrtime(true));
    Log::shouldHaveReceived('warning')->once()->with('Wald Analyse-only stopped safely', Mockery::on(function (array $context): bool {
        expect($context['problem_code'])->toBe('resource_limit_exceeded')
            ->and($context['limit'])->toBe('rows')
            ->and($context['maximum'])->toBe(20001)
            ->and(Str::isUuid($context['diagnostic_id']))->toBeTrue()
            ->and($context['elapsed_ms'])->toBeGreaterThanOrEqual(0)
            ->and($context['peak_memory_bytes'])->toBeGreaterThan(0)
            ->and(json_encode($context))->not->toContain('PRIVATE');

        return true;
    }));
});

it('never logs arbitrary exception codes messages or contexts', function (): void {
    config(['wald_import.dry_run_diagnostics' => true]);
    Log::spy();
    (new WaldDryRunDiagnostics)->stopped(new AnalysisProblem('PRIVATE-SOURCE', ['limit' => 'PRIVATE-PATH', 'maximum' => 'PRIVATE-ROW']), hrtime(true));
    (new WaldDryRunDiagnostics)->stopped(new InvalidArgumentException('PRIVATE-PASSWORD'), hrtime(true));
    Log::shouldHaveReceived('warning')->twice()->with('Wald Analyse-only stopped safely', Mockery::on(fn (array $context): bool => ! str_contains(json_encode($context), 'PRIVATE') && $context['limit'] === null && $context['maximum'] === null
    ));
});

it('distinguishes known payload errors without logging their payload', function (string $code): void {
    config(['wald_import.dry_run_diagnostics' => true]);
    Log::spy();
    (new WaldDryRunDiagnostics)->stopped(new InvalidArgumentException($code), hrtime(true));
    Log::shouldHaveReceived('warning')->once()->with('Wald Analyse-only stopped safely', Mockery::on(fn (array $context): bool => $context['problem_code'] === $code));
})->with(['knowledge_payload_limit', 'transient_evidence_hash_limit', 'knowledge_requires_plain_data']);

it('logs a genuine stopped HTTP analysis privately without changing response or business data', function (): void {
    config(['wald_import.pilot_available' => true, 'wald_import.dry_run_diagnostics' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = User::factory()->create(['is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::where('identifier', 'fenster_office_staff')->value('id')]);
    Log::spy();
    $file = UploadedFile::fake()->createWithContent('PRIVATE-FILENAME.csv', "CustomerNo,Call No.,Site Name,Plot Ref,Call Type\n\"PRIVATE-UNFINISHED\n");
    $this->actingAs($office)->from(route('office.workspace.imports.dry-run'))
        ->post(route('office.workspace.imports.dry-run.analyse'), ['workbook' => $file])
        ->assertRedirect(route('office.workspace.imports.dry-run'))
        ->assertSessionHasErrors(['workbook' => 'Analysis stopped safely. The workbook could not be analysed within the supported structure and resource limits. No data was applied.']);
    Log::shouldHaveReceived('warning')->once()->with('Wald Analyse-only stopped safely', Mockery::on(fn (array $context): bool => $context['problem_code'] === 'invalid_csv' && ! str_contains(json_encode($context), 'PRIVATE')
    ));
    foreach (['wald_pilot_uploads', 'wald_import_runs', 'wald_source_bindings', 'projected_plots', 'call_off_requests', 'portal_notifications'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
});

it('does not grant import access when diagnostics are enabled', function (): void {
    config(['wald_import.pilot_available' => false, 'wald_import.dry_run_diagnostics' => true]);
    $office = User::factory()->create(['is_active' => true, 'is_preview_user' => false,
        'portal_role_id' => PortalRole::where('identifier', 'fenster_office_staff')->value('id')]);
    Log::spy();
    $this->actingAs($office)->post(route('office.workspace.imports.dry-run.analyse'), [
        'workbook' => UploadedFile::fake()->createWithContent('example.csv', "A,B\n1,2\n"),
    ])->assertForbidden();
    Log::shouldNotHaveReceived('warning');
});
