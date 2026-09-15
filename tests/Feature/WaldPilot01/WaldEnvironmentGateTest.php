<?php

use App\Models\PortalRole;
use App\Models\User;
use App\SourceImport\Integration\PilotImportPolicy;
use App\SourceImport\Integration\WaldPilotAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function resolvedWaldImportConfig(?string $value): array
{
    $repository = Env::getRepository();
    $wasPresent = $repository->has('WALD_IMPORT_AVAILABLE');
    $previous = $repository->get('WALD_IMPORT_AVAILABLE');

    try {
        $value === null
            ? $repository->clear('WALD_IMPORT_AVAILABLE')
            : $repository->set('WALD_IMPORT_AVAILABLE', $value);

        return require config_path('wald_import.php');
    } finally {
        $wasPresent
            ? $repository->set('WALD_IMPORT_AVAILABLE', $previous)
            : $repository->clear('WALD_IMPORT_AVAILABLE');
    }
}

it('fails the actual environment config gate closed unless the exact value is true', function (?string $value, bool $expected): void {
    expect(resolvedWaldImportConfig($value)['pilot_available'])->toBe($expected);
})->with([
    'unset' => [null, false],
    'empty' => ['', false],
    'false' => ['false', false],
    'uppercase false' => ['FALSE', false],
    'zero' => ['0', false],
    'no' => ['no', false],
    'off' => ['off', false],
    'garbage' => ['garbage', false],
    'one' => ['1', false],
    'yes' => ['yes', false],
    'on' => ['on', false],
    'parenthesized true' => ['(true)', false],
    'padded true' => [' true ', false],
    'true' => ['true', true],
    'uppercase true' => ['TRUE', true],
]);

it('stores only the strict resolved boolean in Laravel cached configuration', function (string $value, bool $expected): void {
    $relativeCache = 'storage/framework/testing/wald-config-'.bin2hex(random_bytes(8)).'.php';
    $cache = base_path($relativeCache);
    $process = new Process(
        [PHP_BINARY, 'artisan', 'config:cache'],
        base_path(),
        ['APP_CONFIG_CACHE' => $relativeCache, 'WALD_IMPORT_AVAILABLE' => $value],
    );

    try {
        $process->mustRun();
        $cached = require $cache;
        expect($cached['wald_import']['pilot_available'])->toBe($expected);
    } finally {
        if (is_file($cache)) {
            unlink($cache);
        }
    }
})->with([
    'malformed cached value' => ['garbage', false],
    'true cached value' => ['TRUE', true],
]);

it('requires the strict environment gate the application setting and current Office authority', function (): void {
    $office = User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
    ]);
    $availability = new WaldPilotAvailability;

    DB::table('wald_pilot_settings')->where('key', WaldPilotAvailability::KEY)->update(['enabled' => true]);
    config(['wald_import.pilot_available' => 'malformed']);
    expect($availability->environmentAllows())->toBeFalse()
        ->and($availability->enabled())->toBeFalse();

    config(['wald_import.pilot_available' => resolvedWaldImportConfig('false')['pilot_available']]);
    expect($availability->enabled())->toBeFalse();

    config(['wald_import.pilot_available' => resolvedWaldImportConfig('true')['pilot_available']]);
    DB::table('wald_pilot_settings')->where('key', WaldPilotAvailability::KEY)->update(['enabled' => false]);
    expect($availability->enabled())->toBeFalse();

    DB::table('wald_pilot_settings')->where('key', WaldPilotAvailability::KEY)->update(['enabled' => true]);
    expect($availability->enabled())->toBeTrue()
        ->and((new PilotImportPolicy)->authorize($office)->is($office))->toBeTrue();

    config(['wald_import.pilot_available' => resolvedWaldImportConfig('malformed')['pilot_available']]);
    expect($availability->enabled())->toBeFalse();
});
