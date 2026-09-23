<?php

use App\Enums\PortalRoleIdentifier;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function shellDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return new DOMXPath($document);
}

test('shared Office navigation identifies each current workspace and keeps account actions out of page content', function (string $route, string $label) {
    config(['wald_import.pilot_available' => true]);
    DB::table('wald_pilot_settings')->where('key', 'wald_import_pilot_enabled')->update(['enabled' => true]);
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['customer_organisation_id' => null, 'is_preview_user' => false]);
    $response = $this->actingAs($office)->get(route($route))->assertOk();
    $xpath = shellDocument($response->getContent());
    $current = $xpath->query('//aside//a[@aria-current="page"]');
    expect($current->length)->toBe(1)
        ->and(trim($current->item(0)->textContent))->toBe($label)
        ->and($xpath->query('//aside//form[@method="GET"]')->length)->toBe(0)
        ->and($xpath->query('//aside//form[contains(@action,"logout")]')->length)->toBe(1)
        ->and($xpath->query('//main//form[contains(@action,"logout")]')->length)->toBe(0);
    $response->assertSee('Customers &amp; access', false)->assertSee('Your account')->assertSee('Menu');
})->with([
    ['dashboard', 'Dashboard'],
    ['office.workspace.customers.index', 'Customers'],
    ['office.workspace.users.index', 'Users'],
    ['office.workspace.imports', 'Imports'],
    ['office.workspace.amendments.index', 'Amendments'],
    ['portal.review-requests', 'Review Requests'],
    ['office.workspace.settings.wald', 'Settings'],
]);

test('Review Requests in-page filters render once outside navigation with their existing form destination', function () {
    $office = User::factory()->role(PortalRoleIdentifier::FensterOfficeStaff)->create(['is_preview_user' => false]);
    $response = $this->actingAs($office)->get(route('portal.review-requests'))->assertOk();
    $xpath = shellDocument($response->getContent());
    expect($xpath->query('//aside//form[@aria-label="Review request filters"]')->length)->toBe(0)
        ->and($xpath->query('//main//form[@aria-label="Review request filters"]')->length)->toBe(1)
        ->and($xpath->query('//main//form[@aria-label="Review request filters" and @method="GET" and @action="'.route('portal.review-requests').'"]')->length)->toBe(1);
    $response->assertSee(':inert="sidebarOpen && !desktop"', false);
});

test('Site User navigation preserves site context and excludes Office actions', function (PortalRoleIdentifier $role) {
    $site = Site::factory()->create();
    $user = User::factory()->role($role)->create(['customer_organisation_id' => $site->customer_organisation_id]);
    $user->assignedSites()->attach($site);
    $response = $this->actingAs($user)->withSession([EnsureActiveSiteIsAssigned::SESSION_KEY => $site->id])
        ->get(route('portal.site-dashboard'))->assertOk()->assertSee('Switch site &amp; filters', false)
        ->assertSee('Switch site')->assertDontSee('Review Requests')->assertDontSee('Settings')->assertDontSee('Purge');
    $xpath = shellDocument($response->getContent());
    expect($xpath->query('//aside//a[@aria-current="page"]')->length)->toBe(1)
        ->and($xpath->query('//aside//form[not(contains(@action,"logout"))]')->length)->toBe(0)
        ->and($xpath->query('//main//form[contains(@action,"sites/active")]')->length)->toBe(1)
        ->and($xpath->query('//main//form[@aria-label="Filter plot overview"]')->length)->toBe(1);
})->with(PortalRoleIdentifier::siteRoles());

test('shared header supports escaped title optional context and a separate action area', function () {
    $html = Blade::render('<x-page-header :title="$title" description="Useful context"><x-slot:context>3 sites</x-slot:context><x-slot:actions><a href="/example">Open</a></x-slot:actions></x-page-header>', ['title' => '<script>Unsafe</script>']);
    $xpath = shellDocument($html);
    expect($xpath->query('//h1')->length)->toBe(1);
    expect($html)->toContain('&lt;script&gt;Unsafe&lt;/script&gt;', 'Useful context', '3 sites', 'Open')
        ->not->toContain('<script>Unsafe</script>');
});
