<?php

use Composer\InstalledVersions;
use Filament\Facades\Filament;
use Illuminate\Mail\Markdown;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Livewire\Livewire;
use Tests\Support\SecurityDependencyProbe;

it('retains the minimum patched dependency versions', function (): void {
    foreach ([
        'filament/filament' => '5.7.6',
        'livewire/livewire' => '4.3.4',
        'league/commonmark' => '2.10.0',
    ] as $package => $minimum) {
        expect(version_compare(InstalledVersions::getVersion($package), $minimum, '>='))->toBeTrue();
    }
});

it('renders framework mail markdown with the patched CommonMark converter', function (): void {
    $html = (string) Markdown::parse('A **Customer Portal** notification.');

    expect($html)->toContain('<strong>Customer Portal</strong>');
});

it('rejects form-feed-prefixed event attributes in the patched optional CommonMark extension', function (): void {
    $environment = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false]);
    $environment->addExtension(new CommonMarkCoreExtension);
    $environment->addExtension(new AttributesExtension);
    $converter = new MarkdownConverter($environment);

    $validHtml = (string) $converter->convert("safe\n{.qa-class}");
    $html = (string) $converter->convert('safe{'."\x0C".'onclick="alert(1)"}');

    expect($validHtml)->toContain('qa-class');
    expect($html)->not->toContain('onclick')->not->toContain('alert(1)');
});

it('keeps the installed Livewire rendering and update cycle compatible and escaped', function (): void {
    Livewire::test(SecurityDependencyProbe::class)
        ->assertSee('Security probe')
        ->set('message', '<script>alert(1)</script>')
        ->assertSet('message', '<script>alert(1)</script>')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('does not introduce a Filament authentication panel into the portal', function (): void {
    expect(Filament::getPanels())->toBe([]);
    $this->get('/login')->assertOk()->assertSee('Sign in');
});
