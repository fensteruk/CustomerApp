<?php

use App\Enums\PortalRoleIdentifier;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\Site;
use App\Models\User;
use App\SourceImport\Integration\ExportOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

uses(RefreshDatabase::class);

function xlsUploadFixture(): string
{
    $path = tempnam(sys_get_temp_dir(), 'wald-upload-xls-');
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $headers = ['CustomerNo', 'Call No.', 'Site Name', 'Plot Ref', 'Call Type', 'Complete', 'VS'];
    foreach ($headers as $index => $header) {
        $sheet->setCellValue([$index + 1, 1], $header);
    }
    for ($row = 2; $row <= 5; $row++) {
        foreach (['CODE-1', 1000 + $row, 'Example Site', 'Plot '.($row - 1), 'PC1', 'No', 2] as $index => $value) {
            $sheet->setCellValue([$index + 1, $row], $value);
        }
    }
    (new Xls($book))->save($path);
    $book->disconnectWorksheets();

    return $path;
}

it('accepts a real binary XLS through the Office upload route and retains a private XLS artifact', function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', PortalRoleIdentifier::FensterOfficeStaff->value)->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
    ]);
    $path = xlsUploadFixture();
    try {
        $response = $this->actingAs($office)->post(route('office.workspace.pilot-import.upload'), [
            'workbook' => new UploadedFile($path, 'redzebra.xls', null, null, true),
            'export_date' => '2026-09-22',
            'export_slot' => 'MORNING',
            'confirmation' => ExportOrder::CONFIRMATION,
            'command_uuid' => (string) Str::uuid(),
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $upload = DB::table('wald_pilot_uploads')->firstOrFail();
        expect($upload->format)->toBe('xls')
            ->and($upload->storage_key)->toEndWith('.xls')
            ->and($upload->state)->not->toBe('FAILED');
    } finally {
        foreach (DB::table('wald_pilot_uploads')->pluck('storage_key') as $key) {
            Storage::build(['driver' => 'local', 'root' => storage_path('app/private/wald-imports')])->delete($key);
        }
        unlink($path);
    }
});

it('denies a fully assigned external site user access to XLS upload', function (): void {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id]);
    $siteUser = User::factory()->create([
        'customer_organisation_id' => $customer->id,
        'portal_role_id' => PortalRole::query()->where('identifier', PortalRoleIdentifier::SiteManager->value)->value('id'),
        'is_active' => true,
    ]);
    $siteUser->assignedSites()->attach($site);
    $path = xlsUploadFixture();
    try {
        $this->actingAs($siteUser)->post(route('office.workspace.pilot-import.upload'), [
            'workbook' => new UploadedFile($path, 'redzebra.xls', null, null, true),
            'export_date' => '2026-09-22',
            'export_slot' => 'MORNING',
            'confirmation' => ExportOrder::CONFIRMATION,
            'command_uuid' => (string) Str::uuid(),
        ])->assertForbidden();
        expect(DB::table('wald_pilot_uploads')->count())->toBe(0);
    } finally {
        unlink($path);
    }
});
