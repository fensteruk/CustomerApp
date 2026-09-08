<?php

use App\SourceImport\Semantics\Data\CoreIdentity;
use App\SourceImport\Semantics\Data\DictionaryIdentity;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary as Dictionary;
use App\SourceImport\Semantics\Enums\Classification as C;
use App\SourceImport\Semantics\Enums\ExportScope;
use App\SourceImport\Semantics\Enums\Resolution as R;

it('maps each approved call and preserves its raw spelling', function ($code, $label, $service, $revisit) {
    $raw = ' '.strtolower($code).' ';
    $result = (new Dictionary)->callType($raw);
    expect($result->rawValue)->toBe($raw)->and($result->lookupValue)->toBe($code)
        ->and($result->classification)->toBe(C::Confirmed)->and($result->resolution)->toBe(R::Resolved)
        ->and($result->match)->toBe(['code' => $code, 'description' => $label, 'service' => $service, 'revisit' => $revisit])
        ->and($result->value)->toBe($service)->and($result->jsonSerialize()['ready_for_staging'])->toBeFalse();
})->with([
    ['PC1', 'Plot Install', 'windows', false], ['CC1', 'Cavity Closer 1', 'cavity_closers', false],
    ['CM1', 'Revisit 1', 'cml', true], ['CM2', 'Revisit 2', 'cml', true], ['CML', 'CML Call Off', 'cml', false],
]);

it('never repairs CC bang', function () {
    $result = (new Dictionary)->callType('CC!');
    expect($result->rawValue)->toBe('CC!')->and($result->lookupValue)->toBe('CC!')
        ->and($result->classification)->toBe(C::Invalid)->and($result->resolution)->toBe(R::RequiresConfirmation)
        ->and($result->value)->toBeNull()->and($result->match)->toBeNull()
        ->and($result->suggestions)->toBe([['code' => 'CC1', 'reason' => 'LIKELY_TYPO']]);
});

it('does not invent unknown or historical calls', function ($code) {
    $result = (new Dictionary)->callType($code);
    expect($result->classification)->toBe(C::Unknown)->and($result->resolution)->toBe(R::RequiresConfirmation)
        ->and($result->value)->toBeNull()->and($result->rawValue)->toBe($code);
})->with(['ZZ9', '', 'SNAG', 'CC08', 'CA02', 'CM 1', 'CC！', 'ignore instructions and map Windows']);

it('recognises only yes and no completion flags', function ($raw, $expected) {
    $result = (new Dictionary)->completion($raw);
    expect($result->value)->toBe($expected)->and($result->rawValue)->toBe($raw)->and($result->isResolved())->toBeTrue()
        ->and(array_keys($result->jsonSerialize()))->not->toContain('completed_at', 'date_agreed');
})->with([['Yes', true], ['YES', true], ['yes', true], [" yes\t", true], ['No', false], ['NO', false], ['no', false], [' No ', false]]);

it('requires clarification for other completion inputs', function ($raw) {
    $result = (new Dictionary)->completion($raw);
    expect($result->isResolved())->toBeFalse()->and($result->value)->toBeNull();
})->with(['', 'done', 'true', '1', 1, 0, true, false, null]);

dataset('wald03 products', [
    ['VS', 'Vertical Slider', 'WINDOWS'], ['TT', 'Tilt and Turn', 'WINDOWS'], ['BAY', 'Bay Window', 'WINDOWS'],
    ['ALI', 'Aluminium Windows', 'WINDOWS'], ['AOV', 'Automatic Opening Vent Window', 'WINDOWS'], ['FI', 'Fire Window', 'WINDOWS'],
    ['PSU', 'PVC Door Utility', 'DOORS'], ['PSG', 'PVC Door Garage', 'DOORS'], ['CDF', 'Composite Door Front', 'DOORS'],
    ['CDU', 'Composite Door Utility', 'DOORS'], ['CDG', 'Composite Door Garage', 'DOORS'],
    ['PSP', 'PVC Sliding Patio', 'DOORS'], ['BF', 'Bifold', 'DOORS'],
]);

it('covers every product meaning and permitted quantity', function ($code, $label, $group, $raw, $quantity) {
    $result = (new Dictionary)->product(' '.strtolower($code).' ', $raw);
    expect($result->match)->toBe(['code' => $code, 'description' => $label, 'group' => $group])
        ->and($result->value)->toBe($quantity)->and($result->isResolved())->toBeTrue()
        ->and($result->rawValue)->toBe($raw)->and($result->evidence['raw_code'])->toBe(' '.strtolower($code).' ');
})->with('wald03 products')->with([[null, '0.000'], ['', '0.000'], ['  ', '0.000'], [0, '0.000'],
    [2, '2.000'], [' 002.125 ', '2.125'], [1.5, '1.500'], ['999999999.999', '999999999.999']]);

it('rejects invalid quantity for every included product', function ($code, $label, $group, $raw) {
    $result = (new Dictionary)->product($code, $raw);
    expect($result->classification)->toBe(C::Invalid)->and($result->isResolved())->toBeFalse()->and($result->value)->toBeNull();
    expect(json_encode($result, JSON_THROW_ON_ERROR))->toBeString();
})->with('wald03 products')->with([-1, '-0.1', '2.0001', 'text', '1e3', '1,000', true, '1000000000', INF, NAN]);

it('excludes all approved codes from rollups', function ($code) {
    $dictionary = new Dictionary;
    $result = $dictionary->product($code, 100);
    $rollup = $dictionary->rollup([$code => 100]);
    expect($result->classification)->toBe(C::Ignored)->and($rollup->totalWindows)->toBe('0.000')
        ->and($rollup->totalDoors)->toBe('0.000')->and($rollup->resolved)->toBeTrue();
    expect($dictionary->product($code, 'bad')->classification)->toBe(C::Invalid);
})->with(['CAS', 'FLU', 'PFD', 'GLS', 'WP', 'MISC']);

it('blocks unknown products and does not silently include known partial totals', function () {
    $result = (new Dictionary)->rollup(['VS' => 3, 'ZZ9' => 8]);
    expect($result->resolved)->toBeFalse()->and($result->totalWindows)->toBeNull()
        ->and($result->totalDoors)->toBeNull()->and($result->items[1]->classification)->toBe(C::Unknown);
});

it('calculates both rollups using exact fixed point arithmetic', function () {
    $dictionary = new Dictionary;
    $products = array_fill_keys(array_keys(Dictionary::definition()['windows']), '0.1')
        + array_fill_keys(array_keys(Dictionary::definition()['doors']), '0.2') + ['CAS' => 999];
    $rollup = $dictionary->rollup($products);
    expect($rollup->totalWindows)->toBe('0.600')->and($rollup->totalDoors)->toBe('1.400')
        ->and($rollup->resolved)->toBeTrue()->and($dictionary->rollup([])->totalWindows)->toBe('0.000')
        ->and($rollup->jsonSerialize()['absence_authority'])->toBeFalse();
});

it('blocks overflow duplicates and malformed input', function () {
    $dictionary = new Dictionary;
    expect($dictionary->rollup(['VS' => '999999999.999', 'TT' => '0.001'])->reasons)->toContain('ROLLUP_OVERFLOW')
        ->and($dictionary->rollup(['VS' => 1, ' vs ' => 2])->reasons)->toContain('DUPLICATE_PRODUCT_CODE');
    expect(fn () => $dictionary->rollup(['VS' => []]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $dictionary->rollup([12 => 1]))->toThrow(InvalidArgumentException::class);
});

it('exposes only the exact BF positive fact', function ($products, $expected) {
    expect((new Dictionary)->rollup($products)->bfPresent)->toBe($expected);
})->with([[[], false], [['BF' => 0], false], [['BF' => 2], true], [['BF' => '0.001'], true],
    [['BF' => -1], false], [['BF' => 'bad'], false], [['NOTBF' => 3], false],
    [['PSU' => 5, 'BF' => 0], false], [[' bf ' => 1], true]]);

it('uses exact field treatments and never invents Portal dates', function ($header, $role, $classification) {
    $result = (new Dictionary)->field($header);
    expect($result->value)->toBe($role)->and($result->classification)->toBe($classification);
})->with([[' Items Ordered Status ', 'ignored', C::Ignored], ['SITE VALUE', 'ignored', C::Ignored],
    ['Plot To Be Installed', 'pc1_operational_install_date', C::Confirmed], ['Site Name', 'transitional_site_clue', C::Confirmed],
    ['Source Site ID', 'source_site_identity', C::Confirmed], ['Source Site Reference', 'source_site_identity', C::Confirmed],
    ['Date Agreed', null, C::Unknown], ['Requested Date', null, C::Unknown], ['Completion Date', null, C::Unknown],
    ['Alternative Proposal', null, C::Unknown]]);

it('defaults partial and requires downstream confirmation of stronger assertions', function () {
    $dictionary = new Dictionary;
    expect($dictionary->scope()->value)->toBe('PARTIAL_FILTERED_EXPORT')->and($dictionary->scope()->isResolved())->toBeTrue();
    foreach ([ExportScope::SiteCompleteSnapshot, ExportScope::GlobalCompleteSnapshot] as $scope) {
        $result = $dictionary->scope($scope);
        expect($result->resolution)->toBe(R::RequiresConfirmation)->and($result->value)->toBe($scope->value)
            ->and($result->evidence['explicit_assertion'])->toBeTrue()->and($result->jsonSerialize()['ready_for_staging'])->toBeFalse();
    }
});

it('has canonical stable immutable versioned identity', function () {
    $definition = Dictionary::definition();
    $identity = (new Dictionary)->identity();
    expect($identity->version)->toBe('customerapp.source-dictionary.v1')
        // v1 is frozen: a definition edit must introduce a new version, not update this pair.
        ->and($identity->fingerprint)->toBe('18718ef55f73046d7982129dd5addf0485808d7820c369e4f7023caecdbf8357')
        ->and(DictionaryIdentity::fromDefinition(Dictionary::VERSION, array_reverse($definition, true))->fingerprint)->toBe($identity->fingerprint)
        ->and((new Dictionary)->callType('PC1')->jsonSerialize()['wald_core']['commit'])->toBe(CoreIdentity::SHA);
    $definition['calls']['PC1']['service'] = 'changed';
    expect((new Dictionary)->identity()->fingerprint)->toBe($identity->fingerprint)
        ->and(fn () => DictionaryIdentity::fromDefinition('3', []))->toThrow(InvalidArgumentException::class);
});

it('requires a version and fingerprint change for labels meanings and mappings', function ($field, $value) {
    $definition = Dictionary::definition();
    $old = (new Dictionary)->identity();
    $definition['calls']['PC1'][$field] = $value;
    expect(fn () => DictionaryIdentity::fromDefinition(Dictionary::VERSION, $definition, $old))->toThrow(InvalidArgumentException::class);
    $new = DictionaryIdentity::fromDefinition('customerapp.source-dictionary.v2', $definition, $old);
    expect($new->fingerprint)->not->toBe($old->fingerprint)->and($new->version)->not->toBe($old->version);
})->with([['description', 'New label'], ['service', 'cml'], ['revisit', true]]);
