<?php

use App\Actions\Administration\UpdateWaldPilotSettingAction;
use App\Models\CustomerOrganisation;
use App\Models\PortalRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function pilotSettingOffice(array $overrides = []): User
{
    return User::factory()->create([
        'customer_organisation_id' => null,
        'portal_role_id' => PortalRole::query()->where('identifier', 'fenster_office_staff')->value('id'),
        'is_active' => true,
        'is_preview_user' => false,
        ...$overrides,
    ]);
}

function enablePilotSettingPayload(bool $enabled, int $version = 1): array
{
    return [
        'enabled' => $enabled ? '1' : '0',
        'lock_version' => $version,
        'reason' => $enabled ? 'Approved supervised weekend management test.' : 'Emergency supervised pilot stop.',
        'confirmation' => $enabled ? UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION : null,
    ];
}

it('defaults off and requires both layers before showing or serving Imports', function (): void {
    $office = pilotSettingOffice();
    expect(DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->value('enabled'))->toBe(0);

    config(['wald_import.pilot_available' => false]);
    $this->actingAs($office)->get(route('office.workspace.settings.wald'))
        ->assertOk()->assertSee('Emergency OFF')->assertDontSee('href="'.route('office.workspace.imports').'"', false);
    $this->actingAs($office)->put(route('office.workspace.settings.wald.update'), enablePilotSettingPayload(true))
        ->assertRedirect(route('office.workspace.settings.wald'));
    $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk()->assertDontSee('WEEKEND PILOT');

    config(['wald_import.pilot_available' => true]);
    $this->actingAs($office)->get(route('office.workspace.imports'))
        ->assertOk()->assertSee('WEEKEND PILOT')->assertSee('ONE SITE AT A TIME');
});

it('audits enable and disable atomically and disable preserves history', function (): void {
    config(['wald_import.pilot_available' => true]);
    $office = pilotSettingOffice();

    $this->actingAs($office)->put(route('office.workspace.settings.wald.update'), enablePilotSettingPayload(true))
        ->assertSessionHasNoErrors();
    expect(DB::table('wald_pilot_setting_events')->count())->toBe(1)
        ->and(DB::table('wald_pilot_settings')->value('lock_version'))->toBe(2);

    $this->actingAs($office)->put(route('office.workspace.settings.wald.update'), enablePilotSettingPayload(false, 2))
        ->assertSessionHasNoErrors();
    expect(DB::table('wald_pilot_setting_events')->count())->toBe(2)
        ->and(DB::table('wald_pilot_settings')->value('enabled'))->toBe(0);
    $this->actingAs($office)->get(route('office.workspace.imports'))->assertOk()->assertDontSee('WEEKEND PILOT');

    $eventId = DB::table('wald_pilot_setting_events')->min('id');
    expect(fn () => DB::table('wald_pilot_setting_events')->where('id', $eventId)->update(['reason' => 'changed']))
        ->toThrow(QueryException::class);
});

it('requires explicit confirmation and rejects stale settings', function (): void {
    $office = pilotSettingOffice();
    $payload = enablePilotSettingPayload(true);
    $payload['confirmation'] = null;
    $this->actingAs($office)->put(route('office.workspace.settings.wald.update'), $payload)
        ->assertSessionHasErrors('confirmation');

    $payload = enablePilotSettingPayload(true, 99);
    $this->actingAs($office)->put(route('office.workspace.settings.wald.update'), $payload)
        ->assertSessionHasErrors('lock_version');
    expect(DB::table('wald_pilot_settings')->value('enabled'))->toBe(0)
        ->and(DB::table('wald_pilot_setting_events')->count())->toBe(0);
});

it('denies external inactive stale and preview identities from changing the setting', function (): void {
    $siteRole = PortalRole::query()->where('identifier', 'site_manager')->value('id');
    $external = pilotSettingOffice([
        'customer_organisation_id' => CustomerOrganisation::factory()->create()->id,
        'portal_role_id' => $siteRole,
    ]);
    $inactive = pilotSettingOffice(['is_active' => false]);
    $preview = pilotSettingOffice(['is_preview_user' => true]);
    $stale = pilotSettingOffice();
    DB::table('users')->where('id', $stale->id)->update(['portal_role_id' => $siteRole]);

    $response = $this->actingAs($external)->put(route('office.workspace.settings.wald.update'), enablePilotSettingPayload(true));
    expect($response->getStatusCode())->toBe(403);
    $response = $this->actingAs($inactive)->put(route('office.workspace.settings.wald.update'), enablePilotSettingPayload(true));
    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('login'));
    foreach ([$preview, $stale] as $actor) {
        $response = $this->actingAs($actor)->put(route('office.workspace.settings.wald.update'), enablePilotSettingPayload(true));
        expect($response->getStatusCode())->toBe(403);
    }
    expect(DB::table('wald_pilot_settings')->value('enabled'))->toBe(0)
        ->and(DB::table('wald_pilot_setting_events')->count())->toBe(0);
});
