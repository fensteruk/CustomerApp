<?php

// Disposable non-empty upgrade rehearsal, never an application/production command.
use App\Actions\CallOff\AgreeRequestedCallOffDateAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Models\CallOffBatch;
use App\Models\CallOffRequest;
use App\Models\PortalRole;
use App\Models\ProjectedPlot;
use App\Models\ProjectedPlotService;
use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = getenv('SPRINT3F_GATE_CONNECTION') ?: 'sqlite';
if ($connection === 'sqlite') {
    $database = storage_path('app/sprint3f-upgrade-'.bin2hex(random_bytes(6)).'.sqlite');
    touch($database);
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null]);
} elseif ($connection === 'mysql' && str_starts_with((string) getenv('DB_DATABASE'), 'portal_sprint3f_gate_')) {
    config(['database.default' => 'mysql', 'database.connections.mysql.url' => null]);
} else {
    throw new RuntimeException('Only SQLite scratch files or explicitly named portal_sprint3f_gate_* MySQL databases are allowed.');
}
config(['cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array']);
DB::purge($connection);
if (Schema::getTables() !== []) {
    throw new RuntimeException('The rehearsal requires a newly created empty database; no existing tables will be removed.');
}

$newMigration = database_path('migrations/2026_09_03_000014_add_date_amendment_metadata.php');
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $file): bool => $file !== $newMigration));
if (Artisan::call('migrate', ['--path' => $baseline, '--realpath' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Baseline migration failed.');
}
foreach (PortalRoleIdentifier::cases() as $role) {
    PortalRole::firstOrCreate(['identifier' => $role->value], ['name' => $role->label()]);
}
$site = Site::factory()->create();
$customer = User::factory()->role(PortalRoleIdentifier::SiteManager)->create(['customer_organisation_id' => $site->customer_organisation_id]);
$office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create();
$customer->assignedSites()->attach($site);
$plot = ProjectedPlot::factory()->create(['site_id' => $site->id]);
$service = ProjectedPlotService::query()->create(['projected_plot_id' => $plot->id, 'service_identifier' => CallOffServiceType::Windows, 'source_present' => true]);
$date = now()->addWeeks(5)->nextWeekday()->toDateString();
$batch = CallOffBatch::factory()->create(['site_id' => $site->id, 'submitted_by_user_id' => $customer->id, 'requested_date' => $date]);
$request = CallOffRequest::query()->create([
    'call_off_batch_id' => $batch->id, 'projected_plot_id' => $plot->id,
    'projected_plot_service_id' => $service->id, 'service_identifier' => CallOffServiceType::Windows,
    'requested_date' => $date, 'status' => CallOffRequestStatus::AwaitingFenster,
]);
app(AgreeRequestedCallOffDateAction::class)->handle($office, $request);
$before = [];
foreach (['call_off_requests', 'call_off_date_negotiations', 'call_off_date_proposals', 'call_off_status_histories', 'portal_notifications'] as $table) {
    $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
}
if (Artisan::call('migrate', ['--path' => [$newMigration], '--realpath' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Additive amendment migration failed.');
}
foreach ($before as $table => $rows) {
    $after = DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    if (count($rows) !== count($after)) {
        throw new RuntimeException("Row count changed for {$table}.");
    }
    foreach ($rows as $index => $row) {
        if ($row !== Arr::only($after[$index], array_keys($row))) {
            throw new RuntimeException("Historical values changed for {$table}.");
        }
    }
}
if (! Schema::hasColumns('call_off_date_negotiations', ['requested_by_user_id', 'reason_code', 'resulting_agreed_date', 'is_urgent'])) {
    throw new RuntimeException('Amendment columns missing.');
}
echo json_encode(['result' => 'passed', 'connection' => $connection, 'preserved_tables' => array_keys($before), 'fixture_requests' => 1], JSON_THROW_ON_ERROR).PHP_EOL;
