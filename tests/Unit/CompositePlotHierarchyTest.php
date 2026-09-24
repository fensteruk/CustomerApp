<?php

use App\SourceImport\Integration\CompositePlotHierarchy;
use App\SourceImport\Integration\HierarchySuggestion;

it('parses exact customer site and string plot hierarchy with approved spaced separators', function (string $raw, string $plot): void {
    $result = (new CompositePlotHierarchy)->parse($raw);

    expect($result['valid'])->toBeTrue()
        ->and($result['customer'])->toBe('Vistry')
        ->and($result['site'])->toBe('Countryside 2D')
        ->and($result['plot_source'])->toBe('Plot '.$plot)
        ->and($result['plot'])->toBe($plot)
        ->and($result['raw'])->toBe($raw);
})->with([
    ['Vistry - Countryside 2D - Plot 776', '776'],
    ['Vistry – Countryside 2D – Plot 776', '776'],
    ['Vistry — Countryside 2D — Plot Com 4', 'Com 4'],
    ['  Vistry  -  Countryside 2D  -  Plot Comms2  ', 'Comms2'],
    ['Vistry-Countryside 2D-Plot Comms2', 'Comms2'],
]);

it('offers the approved Little Cotton Farm pattern only as a confirmation suggestion', function (): void {
    $suggestion = (new HierarchySuggestion)->forIssue('FNA2664', 'Little Cotton Farm 117-144...- Baker Estates Ltd222');
    expect($suggestion)->toBe(['customer' => 'Baker Estates Ltd', 'site' => 'Little Cotton Farm 117-144',
        'plot' => '222', 'requires_office_confirmation' => true])
        ->and((new HierarchySuggestion)->forIssue('OTHER', 'Little Cotton Farm 117-144...- Baker Estates Ltd222'))->toBeNull()
        ->and((new HierarchySuggestion)->forIssue('FNA2664', 'Little Cotton Farm 117-144...- Baker Estates LtdABC'))->toBeNull();
});

it('parses the exact Northam mixed separator format and all trailing digits', function (): void {
    $parsed = (new CompositePlotHierarchy)->parse('Vistry - Northam PH3-33842', 'FNA2561');
    expect($parsed['valid'])->toBeTrue()
        ->and($parsed['customer'])->toBe('Vistry')
        ->and($parsed['site'])->toBe('Northam PH3')
        ->and($parsed['plot'])->toBe('33842')
        ->and((new CompositePlotHierarchy)->parse('Vistry - Northam PH3-33842', 'OTHER')['valid'])->toBeFalse()
        ->and((new CompositePlotHierarchy)->parse('Vistry - Northam PH3-338A', 'FNA2561')['valid'])->toBeFalse();
});

it('blocks malformed or unspaced hierarchy without guessing', function (string $raw): void {
    expect((new CompositePlotHierarchy)->parse($raw)['valid'])->toBeFalse();
})->with([
    'Vistry – Countryside 2D',
    'Vistry – Countryside 2D – Plot 776 – Extra',
    '– Countryside 2D – Plot 776',
    'Vistry – – Plot 776',
    'Vistry – Countryside 2D – Plot',
    'Pearce Construction-Anchorwood 77661-Extra-Plot 049',
]);
