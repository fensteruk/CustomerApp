<?php

use App\Enums\AdministrativeAction;
use App\Enums\PortalRoleIdentifier;
use App\Models\AdministrativeAudit;
use App\Models\CustomerOrganisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires disposable ADMIN-SITE02 MySQL 8.4.');
    }

    if (config('database.connections.mysql.host') !== '127.0.0.1'
        || ! str_starts_with(DB::connection()->getDatabaseName(), 'customerapp_admin_site02')
        || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
        throw new RuntimeException('disposable_admin_site02_mysql84_required');
    }
});

it('serializes two Office customer renames using the expected version', function (): void {
    [$first, $second] = adminSite02MysqlOffices();
    $customer = CustomerOrganisation::factory()->create();

    $results = adminSite02MysqlRace([
        ['customer-rename', $first->id, $customer->id, 'First race name', 1],
        ['customer-rename', $second->id, $customer->id, 'Second race name', 1],
    ]);

    expect($results->where('ok', true))->toHaveCount(1)
        ->and($customer->fresh()->lock_version)->toBe(2)
        ->and($customer->fresh()->name)->toBeIn(['First race name', 'Second race name'])
        ->and(AdministrativeAudit::query()->where('entity_uuid', $customer->uuid)->where('action', AdministrativeAction::Renamed)->count())->toBe(1);
});

it('prevents customer edit and deactivation from silently both committing', function (): void {
    [$first, $second] = adminSite02MysqlOffices();
    $customer = CustomerOrganisation::factory()->create(['name' => 'Edit versus deactivate']);

    $results = adminSite02MysqlRace([
        ['customer-rename', $first->id, $customer->id, 'Edited customer', 1],
        ['customer-deactivate', $second->id, $customer->id, 'Concurrent pause.', 1],
    ]);

    $fresh = $customer->fresh();
    expect($results->where('ok', true))->toHaveCount(1)->and($fresh->lock_version)->toBe(2);
    expect([$fresh->name, $fresh->is_active])->toBeIn([
        ['Edited customer', true],
        ['Edit versus deactivate', false],
    ]);
    expect(AdministrativeAudit::query()->where('entity_uuid', $customer->uuid)->count())->toBe(1);
});

it('prevents concurrent deactivate and reactivate from producing contradictory history', function (): void {
    [$first, $second] = adminSite02MysqlOffices();
    $customer = CustomerOrganisation::factory()->create();

    $results = adminSite02MysqlRace([
        ['customer-deactivate', $first->id, $customer->id, 'Pause.', 1],
        ['customer-reactivate', $second->id, $customer->id, 'Resume.', 1],
    ]);

    expect($results->where('ok', true))->toHaveCount(1)
        ->and($customer->fresh()->is_active)->toBeFalse()
        ->and($customer->fresh()->lock_version)->toBe(2)
        ->and(AdministrativeAudit::query()->where('entity_uuid', $customer->uuid)->count())->toBe(1)
        ->and(AdministrativeAudit::query()->where('entity_uuid', $customer->uuid)->firstOrFail()->action)->toBe(AdministrativeAction::Deactivated);
});

it('allows only one same-customer same-name site create under a real race', function (): void {
    [$first, $second] = adminSite02MysqlOffices();
    $customer = CustomerOrganisation::factory()->create();

    $results = adminSite02MysqlRace([
        ['site-create', $first->id, $customer->id, 'Race Site', '__NULL__'],
        ['site-create', $second->id, $customer->id, 'Race Site', '__NULL__'],
    ]);

    expect($results->where('ok', true))->toHaveCount(1)
        ->and(Site::query()->where('customer_organisation_id', $customer->id)->where('name', 'Race Site')->count())->toBe(1);
    $site = Site::query()->where('customer_organisation_id', $customer->id)->where('name', 'Race Site')->firstOrFail();
    expect(AdministrativeAudit::query()->where('entity_uuid', $site->uuid)->where('action', AdministrativeAction::Created)->count())->toBe(1);
});

it('prevents site edit and deactivation from silently both committing', function (): void {
    [$first, $second] = adminSite02MysqlOffices();
    $customer = CustomerOrganisation::factory()->create();
    $site = Site::factory()->create(['customer_organisation_id' => $customer->id, 'name' => 'Initial site']);

    $results = adminSite02MysqlRace([
        ['site-update', $first->id, $customer->id, $site->id, 'Edited site', '__NULL__', 1],
        ['site-deactivate', $second->id, $customer->id, $site->id, 'Concurrent pause.', 1],
    ]);

    $fresh = $site->fresh();
    expect($results->where('ok', true))->toHaveCount(1)->and($fresh->lock_version)->toBe(2);
    expect([$fresh->name, $fresh->is_active])->toBeIn([
        ['Edited site', true],
        ['Initial site', false],
    ]);
    expect(AdministrativeAudit::query()->where('entity_uuid', $site->uuid)->count())->toBe(1);
});

/** @return array{User, User} */
function adminSite02MysqlOffices(): array
{
    return [
        User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]),
        User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]),
    ];
}

/** @param array<int, array<int, int|string>> $workers */
function adminSite02MysqlRace(array $workers): Collection
{
    $barrier = storage_path('framework/testing/admin-site02-race-'.Str::uuid());
    $processes = collect($workers)->map(fn (array $worker): Process => new Process([
        PHP_BINARY,
        base_path('tests/Support/CustomerAdminSite02MysqlWorker.php'),
        ...$worker,
        $barrier,
    ], base_path()));

    try {
        $processes->each(fn (Process $process) => $process->setTimeout(30)->start());
        usleep(300_000);
        touch($barrier);

        return $processes->map(function (Process $process): array {
            $process->wait();

            expect($process->getExitCode())->toBe(0);

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        });
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }

        if (file_exists($barrier)) {
            unlink($barrier);
        }
    }
}
