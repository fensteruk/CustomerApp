<?php

use App\SourceImport\Integration\MasterPlotHierarchy;

it('reads wrapped site sections while retaining internal site hyphens and plot labels', function (): void {
    $parsed = (new MasterPlotHierarchy)->parse("Karsal\n - \nZZ  - Warehouse - (Fenster N Abbot)\n - Plot 3257");

    expect($parsed['valid'])->toBeTrue()
        ->and($parsed['customer'])->toBe('Karsal')
        ->and($parsed['site'])->toBe('ZZ - Warehouse - (Fenster N Abbot)');
});

it('requires explicit customer site and Plot components in a master section', function (): void {
    expect((new MasterPlotHierarchy)->parse('Karsal - Plot 3257')['valid'])->toBeFalse()
        ->and((new MasterPlotHierarchy)->parse('Karsal - Warehouse - Plot')['valid'])->toBeFalse()
        ->and((new MasterPlotHierarchy)->parse('Vista Panels - ZZ - Warehouse -Plot VP-ORDERS')['valid'])->toBeTrue();
});

it('parses compact customer-site-plot forms generically without a customer-code exception', function (): void {
    $parser = new MasterPlotHierarchy;
    $compact = $parser->parse('Pearce Construction-Anchorwood 77661-Plot 049');
    $northam = $parser->parse('Vistry - Northam PH3-338', 'ANY-CODE');

    expect($compact['valid'])->toBeTrue()
        ->and($compact['customer'])->toBe('Pearce Construction')
        ->and($compact['site'])->toBe('Anchorwood 77661')
        ->and($northam)->toMatchArray(['valid' => true, 'customer' => 'Vistry', 'site' => 'Northam PH3'])
        ->and($parser->parse('Builder-Site-Name-Plot 01')['valid'])->toBeFalse();
});
