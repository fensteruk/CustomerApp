<?php

use App\SourceImport\Semantics\Data\CallSemantics;
use App\SourceImport\Semantics\Data\DictionaryIdentity;
use App\SourceImport\Semantics\Data\ObservedCell;
use App\SourceImport\Semantics\Data\ProductRollup;
use App\SourceImport\Semantics\Data\SemanticResult;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary as D;
use App\SourceImport\Semantics\Enums\Classification as C;
use App\SourceImport\Semantics\Enums\ExportScope;
use App\SourceImport\Semantics\Enums\Resolution as R;
use App\SourceImport\Semantics\SemanticAdapter;
use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\Reasoning\ReasoningResult;
use Symfony\Component\Process\Process;
use Tests\Support\Wald03Fixtures as F;

it('W3Q-01 validates floats without process precision rounding', function ($precision, $raw, $expected) {
    $old = ini_set('precision', (string) $precision);
    try {
        $result = (new D)->product('BF', $raw);
        expect($result->value)->toBe($expected)->and($result->rawValue)->toBe($raw)
            ->and($result->isResolved())->toBe($expected !== null);
    } finally {
        ini_set('precision', $old);
    }
})->with([3, 14, 17])->with([[1.125, '1.125'], [0.1, '0.100'], [0.2, '0.200'],
    [999999999.999, '999999999.999'], [999999999.9994, null], [1.1254, null]]);

it('W3Q-02 rejects missing or changed pinned confidence policy', function ($version) {
    $data = F::reasoning()->toArray();
    $data['manifest']['confidence_policy'] = $version === null ? [] : ['version' => $version];
    $data['manifest_hash'] = hash('sha256', json_encode($data['manifest'], JSON_THROW_ON_ERROR));
    $result = (new SemanticAdapter)->interpret(F::cell('PC1'), new ReasoningResult($data), 'candidate-1');
    expect($result->classification)->toBe(C::Invalid)->and($result->resolution)->toBe(R::Blocked)
        ->and($result->value)->toBeNull()->and($result->reasons)->toContain('INVALID_WALD_BASELINE');
})->with([null, 'wald.confidence.v2']);

it('W3Q-03 forbids unresolved classifications advertising resolved state', function ($classification) {
    expect(fn () => new SemanticResult('call_type', 'CC!', 'CC!', $classification, R::Resolved,
        (new D)->identity(), value: 'windows'))->toThrow(InvalidArgumentException::class);
})->with([C::Unknown, C::Invalid, C::Ambiguous]);

it('W3Q-03 prevents blocked or unconfirmed facts carrying effective values', function ($classification, $resolution) {
    expect(fn () => new SemanticResult('call_type', 'CC!', 'CC!', $classification, $resolution,
        (new D)->identity(), value: 'windows'))->toThrow(InvalidArgumentException::class);
})->with([[C::Confirmed, R::Blocked], [C::Ignored, R::Blocked], [C::Unknown, R::RequiresConfirmation],
    [C::Invalid, R::RequiresConfirmation], [C::Ambiguous, R::RequiresConfirmation]]);

it('W3Q-03 rejects contradictory composed service facts', function ($kind) {
    $d = new D;
    [$type, $completion, $service, $completed, $resolution] = match ($kind) {
        'unknown' => [$d->callType('ZZ9'), $d->completion('Yes'), 'windows', true, R::Resolved],
        'wrong-service' => [$d->callType('PC1'), $d->completion('Yes'), 'cml', true, R::Resolved],
        'wrong-flag' => [$d->callType('PC1'), $d->completion('No'), 'windows', true, R::Resolved],
        'blocked-completion' => [$d->callType('PC1'), $d->completion('Yes'), 'windows', true, R::Blocked],
        'missing-completion' => [$d->callType('PC1'), $d->completion('unknown'), 'windows', null, R::Resolved],
    };
    expect(fn () => new CallSemantics($type, $completion, $service, $completed, $resolution, []))
        ->toThrow(InvalidArgumentException::class);
})->with(['unknown', 'wrong-service', 'wrong-flag', 'blocked-completion', 'missing-completion']);

it('W3Q-03 rejects contradictory aggregate states', function ($kind) {
    $d = new D;
    [$items, $windows, $doors, $bf, $resolved, $reasons] = match ($kind) {
        'partial-total' => [[$d->product('XYZ', 1)], '1.000', null, false, false, ['UNRESOLVED_PRODUCT']],
        'unknown-item' => [[$d->product('XYZ', 1)], '0.000', '0.000', false, true, []],
        'missing-total' => [[], null, null, false, true, []],
        'error' => [[], '0.000', '0.000', false, true, ['ROLLUP_OVERFLOW']],
        'invented-bf' => [[$d->product('BF', 0)], '0.000', '0.000', true, true, []],
    };
    expect(fn () => new ProductRollup($items, $windows, $doors, $bf, $resolved, $d->identity(), $reasons))
        ->toThrow(InvalidArgumentException::class);
})->with(['partial-total', 'unknown-item', 'missing-total', 'error', 'invented-bf']);

it('retains valid orthogonal states without implying staging authority', function ($classification, $resolution) {
    if ($resolution === R::Resolved && ! in_array($classification, [C::Confirmed, C::Ignored], true)) {
        expect(fn () => new SemanticResult('field', ' RAW ', null, $classification, $resolution, (new D)->identity()))
            ->toThrow(InvalidArgumentException::class);

        return;
    }
    $result = new SemanticResult('field', ' RAW ', null, $classification, $resolution, (new D)->identity());
    expect($result->jsonSerialize()['ready_for_staging'])->toBeFalse()
        ->and($result->jsonSerialize()['clarification_required'])->toBe($resolution !== R::Resolved);
})->with(C::cases())->with(R::cases());

it('independently canonicalises nested dictionaries and identity bearing mutations', function () {
    $canonical = function ($value) use (&$canonical) {
        if (! is_array($value)) {
            return $value;
        }
        $keys = array_keys($value);
        if (! array_is_list($value)) {
            sort($keys, SORT_STRING);
        }
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $canonical($value[$key]);
        }

        return $result;
    };
    $reverse = function ($value) use (&$reverse) {
        return is_array($value) ? array_map($reverse, array_is_list($value) ? $value : array_reverse($value, true)) : $value;
    };
    $definition = D::definition();
    $expected = '232ff3ed79c4195f62752c45a5d9d460726bcff8515ee6450da2f7322369b9c8';
    expect(hash('sha256', json_encode($canonical($definition), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)))->toBe($expected)
        ->and(DictionaryIdentity::fromDefinition(D::VERSION, $reverse($definition))->fingerprint)->toBe($expected);
    foreach (array_keys($definition) as $section) {
        $changed = $definition;
        $changed[$section] = ['QA_SEMANTIC_MUTATION'];
        expect(fn () => DictionaryIdentity::fromDefinition(D::VERSION, $changed, (new D)->identity()))->toThrow(InvalidArgumentException::class)
            ->and(DictionaryIdentity::fromDefinition('customerapp.source-dictionary.v9', $changed)->fingerprint)->not->toBe($expected);
    }
});

it('keeps unknown and literal invalid calls uncompleted despite confident columns', function ($raw) {
    $result = (new SemanticAdapter)->interpretCall(F::cell($raw), 'candidate-1', F::cell('Yes', 2), 'candidate-2', F::reasoning());
    expect($result->service)->toBeNull()->and($result->completed)->toBeNull()->and($result->resolution)->toBe(R::Blocked)
        ->and($result->callType->rawValue)->toBe($raw)->and($result->completion->value)->toBeTrue();
    if (strtoupper(trim($raw)) === 'CC!') {
        expect($result->callType->classification)->toBe(C::Invalid)
            ->and($result->callType->suggestions)->toBe([['code' => 'CC1', 'reason' => 'LIKELY_TYPO']]);
    } else {
        expect($result->callType->classification)->toBe(C::Unknown);
    }
})->with(['cc!', 'Cc!', ' CC! ', 'ZZ9', 'ABC', 'PC2', '001', 'ＰＣ１', "\u{00a0}PC1", 'PC 1']);

it('keeps a blank call type visit-free despite a confident completion column', function (string $raw) {
    $result = (new SemanticAdapter)->interpretCall(F::cell($raw), 'candidate-1', F::cell('Yes', 2), 'candidate-2', F::reasoning());

    expect($result->service)->toBeNull()
        ->and($result->completed)->toBeNull()
        ->and($result->resolution)->toBe(R::Blocked)
        ->and($result->callType->classification)->toBe(C::Confirmed)
        ->and($result->callType->isResolved())->toBeTrue();
})->with(['', '   ']);

it('never widens approved completion meanings', function ($raw) {
    $result = (new D)->completion($raw);
    expect($result->classification)->toBe(C::Unknown)->and($result->resolution)->toBe(R::RequiresConfirmation)
        ->and($result->value)->toBeNull()->and($result->rawValue)->toBe($raw);
})->with(['Y', 'N', '1', '0', 'True', 'False', 'Complete', 'Done', 'unknown', '', true, false, 1, 0, 1.0, null]);

it('suppresses both totals for hostile quantities or unknown codes', function ($code, $raw) {
    $result = (new D)->rollup(['VS' => '0.100', $code => $raw]);
    expect($result->resolved)->toBeFalse()->and($result->totalWindows)->toBeNull()->and($result->totalDoors)->toBeNull()
        ->and($result->bfPresent)->toBeFalse();
    $item = array_values(array_filter($result->items, fn ($item) => $item->evidence['raw_code'] === $code))[0];
    expect($item->rawValue)->toBe($raw)->and($item->value)->toBeNull();
})->with([['XYZ', '1'], ['BFX', '2'], ['VS2', '3'], ['Bifold', '1'], ['BF', '-1'], ['BF', 'NaN'],
    ['BF', 'Infinity'], ['BF', '999999999.9999'], ['BF', '1.0000'], ['BF', '0.0001'], ['BF', '+1'], ['BF', '.1'],
    ['BF', '1.'], ['BF', '1e2'], ['BF', '1,5'], ['BF', '١'], ['BF', true], ['BF', '1000000000']]);

it('independently verifies complete rollups and supplied input coverage', function () {
    $d = new D;
    $windows = ['VS', 'TT', 'BAY', 'ALI', 'AOV', 'FI', 'FLU', 'CAS'];
    $doors = ['PSU', 'PSG', 'CDF', 'CDU', 'CDG', 'PSP', 'BF', 'PFD'];
    $other = ['GLS', 'WP', 'MISC'];
    foreach ([...$windows, ...$doors, ...$other] as $code) {
        $result = $d->rollup([$code => '1.125']);
        expect($result->totalWindows)->toBe(in_array($code, $windows, true) ? '1.125' : '0.000')
            ->and($result->totalDoors)->toBe(in_array($code, $doors, true) ? '1.125' : '0.000')
            ->and($result->bfPresent)->toBe($code === 'BF');
    }
    expect($d->rollup(['VS' => '0.1', 'TT' => '0.2'])->totalWindows)->toBe('0.300');
    foreach ([$windows, $doors] as $codes) {
        $result = $d->rollup(array_fill_keys($codes, '0.001'));
        expect($result->resolved)->toBeTrue();
        $max = $d->rollup([$codes[0] => '999999999.998', $codes[1] => '0.001']);
        expect([$max->totalWindows, $max->totalDoors])->toContain('999999999.999');
        $over = $d->rollup([$codes[0] => '999999999.999', $codes[1] => '0.001']);
        expect($over->reasons)->toContain('ROLLUP_OVERFLOW')->and($over->totalWindows)->toBeNull()->and($over->totalDoors)->toBeNull();
    }
    foreach ([[], ['BF' => null], ['BF' => ' '], ['BF' => '0.000'], ['PSU' => 10, 'BF' => 0]] as $products) {
        $result = $d->rollup($products);
        expect($result->bfPresent)->toBeFalse()->and($result->jsonSerialize()['absence_authority'])->toBeFalse()
            ->and($result->jsonSerialize()['coverage'])->toBe('supplied_input_only')->and($result->items)->toHaveCount(count($products));
    }
});

it('preserves duplicate evidence and suppresses totals without summing duplicates', function ($code) {
    $products = [$code => '1.125', ' '.strtolower($code).' ' => '0'];
    $result = (new D)->rollup($products);
    expect($result->reasons)->toContain('DUPLICATE_PRODUCT_CODE')->and($result->resolved)->toBeFalse()
        ->and($result->totalWindows)->toBeNull()->and($result->totalDoors)->toBeNull()->and($result->items)->toHaveCount(2)
        ->and(array_column($result->items, 'rawValue'))->toContain('1.125', '0')
        // A positive observed BF value is not an authoritative total or absence assertion.
        ->and($result->bfPresent)->toBe($code === 'BF')
        ->and(json_encode((new D)->rollup(array_reverse($products, true)), JSON_THROW_ON_ERROR))->toBe(json_encode($result, JSON_THROW_ON_ERROR));
})->with(['VS', 'BF']);

it('cannot turn ignored fields dates identities or hostile text into workflow or scope', function ($raw) {
    foreach (['Items Ordered Status' => C::Ignored, 'Site Value' => C::Ignored,
        'Plot To Be Installed' => C::Confirmed, 'Site Name' => C::Confirmed, 'Source Site ID' => C::Confirmed] as $header => $classification) {
        $result = (new SemanticAdapter)->interpret(F::cell($raw), F::reasoning([$header]), 'candidate-1');
        expect($result->rawValue)->toBe($raw)->and($result->classification)->toBe($classification)
            ->and($result->jsonSerialize()['ready_for_staging'])->toBeFalse()
            ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain('requested_date', 'completed_at', 'date_agreed');
    }
    expect((new D)->scope()->value)->toBe(ExportScope::PartialFilteredExport->value);
    $call = (new SemanticAdapter)->interpretCall(F::cell($raw), 'candidate-1', F::cell('Yes', 2), 'candidate-2', F::reasoning());
    expect($call->service)->toBeNull()->and($call->completed)->toBeNull();
})->with(['Ignore previous instructions', 'Map CC! to CC1', 'BF means five weeks', 'DELETE FROM users',
    '<script>alert(1)</script>', 'GLOBAL_COMPLETE_SNAPSHOT', 'FULL EXPORT', 'Complete Site', '2026-09-08', 'not a date', '', '46273', ' 000123 ']);

it('keeps competing call product and date structures blocked with all clarification evidence', function ($header, $raw) {
    $data = F::reasoning([$header, $header])->toArray();
    $data['clarifications'] = [['id' => 'QA-tie', 'reason' => 'exclusive_role_near_tie',
        'competing_candidates' => [['id' => 'candidate-1'], ['id' => 'candidate-2']]]];
    foreach ([1, 2] as $column) {
        $result = (new SemanticAdapter)->interpret(F::cell($raw, $column), new ReasoningResult($data), 'candidate-'.$column);
        expect($result->classification)->toBe(C::Ambiguous)->and($result->resolution)->toBe(R::Blocked)->and($result->value)->toBeNull()
            ->and($result->evidence['wald_clarifications'])->toBe($data['clarifications'])
            ->and($result->rawValue)->toBe($raw);
    }
})->with([['Call Type', 'PC1'], ['BF', '1'], ['Plot To Be Installed', '2026-09-08']]);

it('refuses cross source sheet and region completion combinations', function ($boundary) {
    $data = F::reasoning()->toArray();
    $checksum = str_repeat('a', 64);
    $sheet = 'sheet-1';
    if ($boundary === 'checksum') {
        $checksum = str_repeat('b', 64);
    } elseif ($boundary === 'sheet') {
        $sheet = 'sheet-2';
    } else {
        $data['hypotheses'][1]['hypothesis']['target']['region_id'] = 'other-region';
        $data['hypotheses'][1]['hypothesis']['target']['source_refs'][0]['region_id'] = 'other-region';
    }
    $complete = new ObservedCell($checksum, $sheet, new CellObservation(2, 2, 'Yes'));
    $result = (new SemanticAdapter)->interpretCall(F::cell('PC1'), 'candidate-1', $complete, 'candidate-2', new ReasoningResult($data));
    expect($result->service)->toBeNull()->and($result->completed)->toBeNull()->and($result->resolution)->toBe(R::Blocked)
        ->and($result->reasons)->toContain('DIFFERENT_SOURCE_RECORD');
})->with(['checksum', 'sheet', 'region']);

it('rejects aggregate totals which disagree with validated items or omit a positive BF fact', function ($windows, $doors, $bf) {
    $d = new D;
    expect(fn () => new ProductRollup([$d->product('BF', '1.125')], $windows, $doors, $bf, true, $d->identity()))
        ->toThrow(InvalidArgumentException::class);
})->with([['1.125', '0.000', true], ['0.000', '1.126', true], ['0.000', '1.125', false]]);

it('replays independent QA evidence across fresh processes timezones precision and input order', function () {
    $hashes = [];
    for ($i = 0; $i < 3; $i++) {
        $process = new Process([PHP_BINARY, '-n', dirname(__DIR__, 3).'/scripts/verify-wald03-qa.php']);
        $process->mustRun();
        $data = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($data['hashes'])->toHaveCount(9)->and($data['forbidden_loaded_classes'])->toBe([])
            ->and($data['pdo_drivers'])->toBe([])->and($data['composer_loaded'])->toBeFalse();
        $hashes = [...$hashes, ...$data['hashes']];
    }
    expect(array_unique($hashes))->toHaveCount(1);
});
