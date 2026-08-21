<?php

use App\Actions\CallOff\RejectCallOffRequestAction;
use App\Actions\CallOff\SubmitCallOffBatchAction;
use App\Enums\CallOffRequestStatus;
use App\Enums\CallOffServiceType;
use App\Enums\PortalRoleIdentifier;
use App\Http\Controllers\ResubmitRejectedCallOffController;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\CallOffRequest;
use App\Models\CustomerOrganisation;
use App\Models\ProjectedPlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function sprint2aUser(PortalRoleIdentifier $role, ?CustomerOrganisation $organisation = null): User
{
    $organisation ??= CustomerOrganisation::factory()->create();

    return User::factory()->role($role)->create([
        'customer_organisation_id' => $organisation->id,
        'password' => Hash::make('password'),
    ]);
}

function sprint2aSite(User $user, string $name = 'Sprint 2A Site'): Site
{
    $site = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id, 'name' => $name]);
    $user->assignedSites()->attach($site);

    return $site;
}

function sprint2aCallOff(User $user, Site $site, string $reference, CallOffServiceType $service = CallOffServiceType::Windows): CallOffRequest
{
    $plot = ProjectedPlot::factory()->create(['site_id' => $site->id, 'plot_reference' => $reference]);

    return app(SubmitCallOffBatchAction::class)->handle(
        user: $user,
        site: $site,
        serviceType: $service,
        requestedDate: Carbon::today()->addDays(10),
        projectedPlots: [$plot],
        customerResponse: 'Original submission message.',
    )->requests->first();
}

function rejectedSprint2aCallOff(User $user, Site $site, string $reference = 'Rejected Plot'): CallOffRequest
{
    $office = sprint2aUser(PortalRoleIdentifier::FensterOfficeStaff, $user->customerOrganisation);
    $office->assignedSites()->attach($site);
    $request = sprint2aCallOff($user, $site, $reference);
    app(RejectCallOffRequestAction::class)->handle($office, $request, 'Choose another date.', 'Private reason.');

    return $request->fresh();
}

it('paginates active-site dashboard requests and combines plot service and status filters', function (): void {
    $user = sprint2aUser(PortalRoleIdentifier::SiteManager);
    $site = sprint2aSite($user);
    $otherSite = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id, 'name' => 'Other Site']);
    $user->assignedSites()->attach($otherSite);

    foreach (range(1, 16) as $number) {
        sprint2aCallOff($user, $site, sprintf('Visible Plot %02d', $number), $number % 2 === 0 ? CallOffServiceType::Cml : CallOffServiceType::Windows);
    }
    sprint2aCallOff($user, $otherSite, 'Hidden Other Site');

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/site-dashboard?plot=Visible+Plot+02&service=cml&status=awaiting_date')
        ->assertOk()
        ->assertSee('Visible Plot 02')
        ->assertDontSee('Hidden Other Site');

    $paginatedResponse = $this->get('/portal/site-dashboard')
        ->assertOk()
        ->assertSee('Visible Plot 01')
        ->assertSee('Showing 1–15 of 16 plots');

    expect(substr_count($paginatedResponse->getContent(), 'Visible Plot'))->toBeGreaterThanOrEqual(15);
});

it('paginates active-site recoverable Trash and excludes expired or foreign records', function (): void {
    $user = sprint2aUser(PortalRoleIdentifier::SiteManager);
    $site = sprint2aSite($user);
    $otherSite = Site::factory()->create(['customer_organisation_id' => $user->customer_organisation_id]);
    $user->assignedSites()->attach($otherSite);

    foreach (range(1, 16) as $number) {
        $request = sprint2aCallOff($user, $site, sprintf('Trash Plot %02d', $number));
        $request->forceFill([
            'status' => CallOffRequestStatus::Withdrawn,
            'active_conflict_key' => null,
            'trashed_at' => now()->subMinutes($number),
            'trash_expires_at' => now()->addDays(7),
        ])->save();
    }
    $expired = sprint2aCallOff($user, $site, 'Expired Trash Plot');
    $expired->forceFill(['status' => CallOffRequestStatus::Withdrawn, 'active_conflict_key' => null, 'trashed_at' => now()->subDays(8), 'trash_expires_at' => now()->subSecond()])->save();
    $foreign = sprint2aCallOff($user, $otherSite, 'Foreign Trash Plot');
    $foreign->forceFill(['status' => CallOffRequestStatus::Withdrawn, 'active_conflict_key' => null, 'trashed_at' => now(), 'trash_expires_at' => now()->addDays(7)])->save();

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/call-offs/trash')
        ->assertOk()
        ->assertSee('Trash Plot 01')
        ->assertDontSee('Trash Plot 16')
        ->assertDontSee('Expired Trash Plot')
        ->assertDontSee('Foreign Trash Plot');
});

it('creates a new linked submitted request from an authorised rejected call-off after confirmation', function (PortalRoleIdentifier $role): void {
    $user = sprint2aUser($role);
    $site = sprint2aSite($user);
    $source = rejectedSprint2aCallOff($user, $site);
    $payload = ['requested_date' => Carbon::today()->addDays(21)->toDateString(), 'customer_response' => 'Updated message.'];

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->post('/portal/call-offs/'.$source->uuid.'/resubmit/confirm', $payload)
        ->assertOk()
        ->assertSee('Review Resubmission')
        ->assertSee('Choose another date.')
        ->assertSee('Updated message.');

    $signature = session(ResubmitRejectedCallOffController::CONFIRMATION_SIGNATURE_SESSION_KEY);
    $this->post('/portal/call-offs/'.$source->uuid.'/resubmit', $payload + ['confirmation_signature' => $signature])
        ->assertRedirect('/portal/site-dashboard');

    $replacement = CallOffRequest::query()->where('resubmitted_from_call_off_request_id', $source->id)->firstOrFail();
    expect($source->fresh()->status)->toBe(CallOffRequestStatus::Rejected)
        ->and($replacement->status)->toBe(CallOffRequestStatus::Submitted)
        ->and($replacement->batch->requested_date->toDateString())->toBe($payload['requested_date'])
        ->and($replacement->batch->customer_response)->toBe('Updated message.');
})->with([PortalRoleIdentifier::SiteManager, PortalRoleIdentifier::AssistantSiteManager, PortalRoleIdentifier::FinishingForeman]);

it('rejects direct or altered final resubmissions and preserves source context', function (): void {
    $user = sprint2aUser(PortalRoleIdentifier::SiteManager);
    $site = sprint2aSite($user);
    $source = rejectedSprint2aCallOff($user, $site);
    $payload = ['requested_date' => Carbon::today()->addDays(21)->toDateString(), 'customer_response' => 'Updated message.'];

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $this->post('/portal/call-offs/'.$source->uuid.'/resubmit', $payload)
        ->assertRedirect('/portal/call-offs/'.$source->uuid.'/resubmit')
        ->assertSessionHasErrors('request');

    $this->post('/portal/call-offs/'.$source->uuid.'/resubmit/confirm', $payload)->assertOk();
    $signature = session(ResubmitRejectedCallOffController::CONFIRMATION_SIGNATURE_SESSION_KEY);
    $this->post('/portal/call-offs/'.$source->uuid.'/resubmit', [
        'requested_date' => Carbon::today()->addDays(22)->toDateString(),
        'customer_response' => 'Updated message.',
        'confirmation_signature' => $signature,
        'service_identifier' => CallOffServiceType::Cml->value,
        'site_id' => 999,
        'plot_id' => 999,
    ])->assertRedirect('/portal/call-offs/'.$source->uuid.'/resubmit')
        ->assertSessionHasErrors('request');

    expect(CallOffRequest::query()->where('resubmitted_from_call_off_request_id', $source->id)->exists())->toBeFalse()
        ->and($source->fresh()->status)->toBe(CallOffRequestStatus::Rejected);
});

it('denies unauthorised source statuses sites organisations revoked access conflicts and completed plots', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $user = sprint2aUser(PortalRoleIdentifier::SiteManager, $organisation);
    $site = sprint2aSite($user);
    $source = rejectedSprint2aCallOff($user, $site);
    $payload = ['requested_date' => Carbon::today()->addDays(21)->toDateString()];

    $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    $source->projectedPlot->forceFill(['is_completed' => true])->save();
    $this->post('/portal/call-offs/'.$source->uuid.'/resubmit/confirm', $payload)->assertRedirect('/portal/site-dashboard');
    $source->projectedPlot->forceFill(['is_completed' => false])->save();

    $user->assignedSites()->detach($site);
    $this->get('/portal/call-offs/'.$source->uuid.'/resubmit')->assertRedirect('/sites/select');
    $user->assignedSites()->attach($site);

    $otherOrganisation = CustomerOrganisation::factory()->create();
    $otherUser = sprint2aUser(PortalRoleIdentifier::SiteManager, $otherOrganisation);
    $otherSite = sprint2aSite($otherUser);
    $this->actingAs($otherUser)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $otherSite->id])
        ->get('/portal/call-offs/'.$source->uuid.'/resubmit')->assertNotFound();
});

it('does not allow Office Staff or non-rejected source requests to use resubmission', function (): void {
    $organisation = CustomerOrganisation::factory()->create();
    $siteUser = sprint2aUser(PortalRoleIdentifier::SiteManager, $organisation);
    $office = sprint2aUser(PortalRoleIdentifier::FensterOfficeStaff, $organisation);
    $site = sprint2aSite($siteUser);
    $office->assignedSites()->attach($site);
    $submitted = sprint2aCallOff($siteUser, $site, 'Submitted Source');
    $approved = sprint2aCallOff($siteUser, $site, 'Approved Source');
    $withdrawn = sprint2aCallOff($siteUser, $site, 'Withdrawn Source');
    $approved->forceFill(['status' => CallOffRequestStatus::Approved])->save();
    $withdrawn->forceFill(['status' => CallOffRequestStatus::Withdrawn, 'active_conflict_key' => null])->save();
    $rejected = rejectedSprint2aCallOff($siteUser, $site, 'Office Source');

    $this->actingAs($siteUser)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id]);
    foreach ([$submitted, $approved, $withdrawn] as $source) {
        $this->get('/portal/call-offs/'.$source->uuid.'/resubmit')->assertForbidden();
    }

    $this->actingAs($office)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get('/portal/call-offs/'.$rejected->uuid.'/resubmit')->assertForbidden();
});

it('blocks resubmission when a new active request conflicts with the rejected source', function (): void {
    $user = sprint2aUser(PortalRoleIdentifier::SiteManager);
    $site = sprint2aSite($user);
    $source = rejectedSprint2aCallOff($user, $site, 'Conflict Source');

    app(SubmitCallOffBatchAction::class)->handle(
        user: $user,
        site: $site,
        serviceType: $source->batch->service_identifier,
        requestedDate: Carbon::today()->addDays(14),
        projectedPlots: [$source->projectedPlot],
    );

    $this->actingAs($user)
        ->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->post('/portal/call-offs/'.$source->uuid.'/resubmit/confirm', [
            'requested_date' => Carbon::today()->addDays(21)->toDateString(),
        ])
        ->assertRedirect('/portal/site-dashboard');

    expect(CallOffRequest::query()->where('resubmitted_from_call_off_request_id', $source->id)->exists())->toBeFalse();
});
