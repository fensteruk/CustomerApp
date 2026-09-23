<?php

use App\Actions\Administration\UpdateWaldPilotSettingAction;
use App\Enums\PortalRoleIdentifier;
use App\Models\User;
use App\SourceImport\Integration\WaldPilotAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null]);
    $this->actingAs($this->office);
});

it('shows truthful saved and effective settings for every combination of gates', function (bool $environment, bool $application): void {
    config(['wald_import.pilot_available' => $environment]);
    DB::table('wald_pilot_settings')->where('key', WaldPilotAvailability::KEY)->update(['enabled' => $application]);
    $response = $this->get(route('office.workspace.settings.wald'))->assertOk()
        ->assertSee('Settings')->assertSee('Office only')->assertSee('Wald Import Pilot')
        ->assertSee('Saved application setting')->assertSee('Deployment access')
        ->assertSee($environment ? 'Allows Wald' : 'Emergency OFF')
        ->assertSee($environment && $application ? 'Pilot available' : 'Pilot unavailable')
        ->assertSee('Supervised use only')->assertSee('No setting changes recorded.')
        ->assertDontSee('Call-Off Lead Times')->assertDontSee('Edit Windows lead time');
    $environment && $application ? $response->assertSee('Open imports') : $response->assertDontSee('Open imports');
})->with([[false, false], [false, true], [true, false], [true, true]]);

it('retains the requested selection and displays associated confirmation errors after an invalid save', function (): void {
    $url = route('office.workspace.settings.wald');
    $this->from($url)->put(route('office.workspace.settings.wald.update'), [
        'enabled' => '1', 'lock_version' => 1, 'reason' => 'Fictional supervised review.',
    ])->assertRedirect($url)->assertSessionHasErrors('confirmation');
    $this->withCookie(config('session.cookie'), session()->getId())->get($url)->assertOk()
        ->assertSee('The setting was not changed.')->assertSee('Fictional supervised review.')
        ->assertSee('id="wald-confirmation-error"', false)->assertSee('aria-describedby="wald-confirmation-error"', false)
        ->assertSee('value="1" required x-model="selectedEnabled" checked', false)
        ->assertSee('Pilot unavailable');
    expect(DB::table('wald_pilot_setting_events')->count())->toBe(0);
});

it('shows reason errors and preserves the saved setting when validation fails', function (): void {
    $url = route('office.workspace.settings.wald');
    $this->from($url)->put(route('office.workspace.settings.wald.update'), [
        'enabled' => '1', 'lock_version' => 1, 'reason' => '',
        'confirmation' => UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION,
    ])->assertSessionHasErrors('reason');
    $this->withCookie(config('session.cookie'), session()->getId())->get($url)->assertOk()
        ->assertSee('id="wald-reason-error"', false)
        ->assertSee('aria-describedby="wald-reason-help wald-reason-error"', false);
    expect(DB::table('wald_pilot_settings')->value('enabled'))->toBe(0);
});

it('presents audited changes with actor old and new values UTC time and escaped reason', function (): void {
    $this->office->update(['name' => '<script>Reviewer</script>']);
    app(UpdateWaldPilotSettingAction::class)->handle($this->office, true, 1, '<script>fictional reason</script>', UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION);
    $this->get(route('office.workspace.settings.wald'))->assertOk()
        ->assertSee('Changed from')->assertSee('Changed by')->assertSee('Reason')->assertSee('UTC')
        ->assertSee('&lt;script&gt;Reviewer&lt;/script&gt;', false)
        ->assertSee('&lt;script&gt;fictional reason&lt;/script&gt;', false)
        ->assertDontSee('<script>fictional reason</script>', false)
        ->assertDontSee('No setting changes recorded.')
        ->assertViewHas('audits', fn ($audits) => $audits->first()->old_value === 0 && $audits->first()->new_value === 1);
});

it('keeps setting history paginated and newest first', function (): void {
    $action = app(UpdateWaldPilotSettingAction::class);
    for ($version = 1; $version <= 21; $version++) {
        $action->handle($this->office, $version % 2 === 1, $version, 'Fictional change '.$version, UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION);
    }
    $this->get(route('office.workspace.settings.wald'))->assertOk()->assertSee('page=2', false)
        ->assertViewHas('audits', fn ($audits) => $audits->count() === 20 && $audits->total() === 21 && $audits->first()->reason === 'Fictional change 21');
});

it('denies each external role from reading or updating Settings', function (PortalRoleIdentifier $role): void {
    $this->actingAs(User::factory()->role($role)->create());
    $this->get(route('office.workspace.settings.wald'))->assertForbidden();
    $this->put(route('office.workspace.settings.wald.update'), [
        'enabled' => '1', 'lock_version' => 1, 'reason' => 'Forged change.',
        'confirmation' => UpdateWaldPilotSettingAction::ENABLE_CONFIRMATION,
    ])->assertForbidden();
    expect(DB::table('wald_pilot_setting_events')->count())->toBe(0);
})->with(PortalRoleIdentifier::siteRoles());

it('requires an active signed-in Office account to read Settings', function (): void {
    auth()->logout();
    $this->get(route('office.workspace.settings.wald'))->assertRedirect(route('login'));
    $this->office->update(['is_active' => false]);
    $this->actingAs($this->office)->get(route('office.workspace.settings.wald'))->assertRedirect(route('login'));
});
