<?php

use App\SourceImport\Semantics\Data\ObservedCell;
use App\SourceImport\Semantics\Enums\Classification as C;
use App\SourceImport\Semantics\Enums\Resolution as R;
use App\SourceImport\Semantics\SemanticAdapter;
use App\Wald\Contracts\CellObservation;
use App\Wald\Contracts\Reasoning\ReasoningResult;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\Wald03Fixtures as F;
use Tests\Support\WaldFixtures;

afterEach(fn () => WaldFixtures::cleanup());

it('combines only known calls and yes no flags into service completion', function ($call, $complete, $service, $completed, $resolution) {
    $result = (new SemanticAdapter)->interpretCall(F::cell($call), 'candidate-1', F::cell($complete, 2), 'candidate-2', F::reasoning());
    expect($result->service)->toBe($service)->and($result->completed)->toBe($completed)->and($result->resolution)->toBe($resolution)
        ->and($result->jsonSerialize()['completion_date'])->toBeNull()->and($result->jsonSerialize()['ready_for_staging'])->toBeFalse();
})->with([
    ['PC1', 'Yes', 'windows', true, R::Resolved], ['CC1', 'No', 'cavity_closers', false, R::Resolved],
    ['CM1', ' YES ', 'cml', true, R::Resolved], ['CM2', 'No', 'cml', false, R::Resolved], ['CML', 'Yes', 'cml', true, R::Resolved],
    ['ZZ9', 'Yes', null, null, R::Blocked], ['CC!', 'Yes', null, null, R::Blocked], ['', 'Yes', null, null, R::Blocked],
    ['PC1', '', 'windows', null, R::Blocked], ['PC1', 'done', 'windows', null, R::Blocked],
]);

it('preserves CC bang semantics through accepted structure', function () {
    $result = (new SemanticAdapter)->interpret(F::cell('CC!'), F::reasoning(), 'candidate-1');
    expect($result->classification)->toBe(C::Invalid)->and($result->resolution)->toBe(R::RequiresConfirmation)
        ->and($result->rawValue)->toBe('CC!')->and($result->match)->toBeNull()->and($result->value)->toBeNull();
});

it('retains raw observation header confidence evidence and identity', function () {
    $cell = new CellObservation(2, 1, ' pc1 ', style: ['number_format' => 'General'], source: ['cell' => 'A2']);
    $observed = new ObservedCell(str_repeat('a', 64), 'sheet-1', $cell);
    $result = (new SemanticAdapter)->interpret($observed, F::reasoning(), 'candidate-1');
    expect($result->rawValue)->toBe(' pc1 ')->and($result->lookupValue)->toBe('PC1')
        ->and($result->evidence['observation'])->toBe($observed->jsonSerialize())
        ->and($result->evidence['wald_candidate']['confidence']['is_probability'])->toBeFalse()
        ->and($result->evidence['wald_candidate']['evidence'][0]['id'])->toBe('synthetic-evidence-1')
        ->and($result->dictionary->version)->toBe('customerapp.source-dictionary.v9');
});

it('classifies supplied field values without dates bindings or workflow side effects', function ($header, $raw, $role, $classification) {
    $result = (new SemanticAdapter)->interpret(F::cell($raw), F::reasoning([$header]), 'candidate-1');
    expect($result->value)->toBe($role)->and($result->classification)->toBe($classification)->and($result->rawValue)->toBe($raw)
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain('requested_date', 'date_agreed', 'alternative_date', 'completed_at');
})->with([['Plot To Be Installed', '2026-09-10', 'pc1_operational_install_date', C::Confirmed],
    ['Site Name', 'Fictional Alpha', 'transitional_site_clue', C::Confirmed],
    ['Source Site ID', '000123', 'source_site_identity', C::Confirmed],
    ['Source Site Reference', 'REF-A', 'source_site_identity', C::Confirmed],
    ['Items Ordered Status', 'PRIVATE_TEXT', 'ignored', C::Ignored], ['Site Value', '9999', 'ignored', C::Ignored]]);

it('uses product dictionary only after accepted structural evidence', function () {
    $result = (new SemanticAdapter)->interpret(F::cell('2'), F::reasoning(['BF']), 'candidate-1');
    expect($result->concept)->toBe('product_quantity')->and($result->match['code'])->toBe('BF')->and($result->value)->toBe('2.000');
    $unknown = (new SemanticAdapter)->interpret(F::cell('999'), F::reasoning(['ZZ9']), 'candidate-1');
    expect($unknown->classification)->toBe(C::Unknown)->and($unknown->value)->toBeNull();
});

it('rejects all nonaccepted structural states without losing tentative dictionary evidence', function ($decision) {
    $data = F::reasoning()->toArray();
    $data['hypotheses'][0]['decision'] = $decision;
    $data['targets'][0]['decision'] = $decision;
    $result = (new SemanticAdapter)->interpret(F::cell('PC1'), new ReasoningResult($data), 'candidate-1');
    expect($result->resolution)->toBe(R::Blocked)->and($result->value)->toBeNull()->and($result->isResolved())->toBeFalse()
        ->and($result->evidence['wald_candidate']['decision'])->toBe($decision);
})->with(['accepted_reviewable', 'clarification_required', 'insufficient_evidence', 'rejected', 'contradiction']);

it('fails closed for inconsistent or mismatched Wald evidence', function ($mutation, $reason) {
    $data = F::reasoning()->toArray();
    $mutation($data);
    $result = (new SemanticAdapter)->interpret(F::cell('PC1'), new ReasoningResult($data), 'candidate-1');
    expect($result->resolution)->toBe(R::Blocked)->and($result->value)->toBeNull()->and($result->reasons)->toContain($reason);
})->with([
    [function (&$d) {
        $d['complete'] = false;
    }, 'INVALID_WALD_BASELINE'],
    [function (&$d) {
        $d['manifest']['structural_engine_version'] = 'wald-0.2.0';
    }, 'INVALID_WALD_BASELINE'],
    [function (&$d) {
        $d['manifest_hash'] = 'wrong';
    }, 'INVALID_WALD_BASELINE'],
    [function (&$d) {
        $d['hypotheses'] = [];
    }, 'MISSING_OR_DUPLICATE_CANDIDATE'],
    [function (&$d) {
        $d['hypotheses'][] = $d['hypotheses'][0];
    }, 'MISSING_OR_DUPLICATE_CANDIDATE'],
    [function (&$d) {
        $d['hypotheses'][0]['hypothesis']['target']['sheet_id'] = 'other';
    }, 'OBSERVATION_PROVENANCE_MISMATCH'],
    [function (&$d) {
        $d['hypotheses'][0]['hypothesis']['target']['source_refs'][0]['source_checksum'] = str_repeat('b', 64);
    }, 'OBSERVATION_PROVENANCE_MISMATCH'],
    [function (&$d) {
        $d['hypotheses'][0]['hypothesis']['target']['source_refs'][0]['range'] = 'A3:A11';
    }, 'OBSERVATION_OUTSIDE_TARGET'],
    [function (&$d) {
        $d['hypotheses'][0]['hypothesis']['target']['source_refs'][0]['range'] = 'bad';
    }, 'INVALID_SOURCE_RANGE'],
    [function (&$d) {
        $d['hypotheses'][0]['hypothesis']['target']['source_refs'][0]['header_refs'] = [];
    }, 'MISSING_HEADER_EVIDENCE'],
    [function (&$d) {
        $d['targets'][0]['leading_candidate'] = 'other';
    }, 'CANDIDATE_NOT_TARGET_LEADER'],
    [function (&$d) {
        $d['hypotheses'][0]['confirmation_flags']['incomplete_rule_evaluation'] = true;
    }, 'WALD_CONFIRMATION_FLAG'],
]);

it('does not treat headers or other-column observations as record values', function ($column, $row, $reason) {
    $result = (new SemanticAdapter)->interpret(F::cell('PC1', $column, $row), F::reasoning(), 'candidate-1');
    expect($result->resolution)->toBe(R::Blocked)->and($result->reasons)->toContain($reason);
})->with([[1, 1, 'OBSERVATION_NOT_DATA_ROW'], [2, 2, 'OBSERVATION_PROVENANCE_MISMATCH'], [1, 20, 'OBSERVATION_OUTSIDE_TARGET']]);

it('never promotes formula caches or spreadsheet errors', function ($type, $formula, $reason) {
    $observed = new ObservedCell(str_repeat('a', 64), 'sheet-1', new CellObservation(2, 1, 'PC1', $type, $formula));
    $result = (new SemanticAdapter)->interpret($observed, F::reasoning(), 'candidate-1');
    expect($result->resolution)->toBe(R::Blocked)->and($result->value)->toBeNull()->and($result->reasons)->toContain($reason)
        ->and($result->evidence['observation']['formula'])->toBe($formula);
})->with([['text', '"PC1"', 'FORMULA_CACHE_REQUIRES_CONFIRMATION'], ['error', null, 'SOURCE_CELL_ERROR']]);

it('does not combine completion across source rows', function () {
    $result = (new SemanticAdapter)->interpretCall(F::cell('PC1'), 'candidate-1', F::cell('Yes', 2, 3), 'candidate-2', F::reasoning());
    expect($result->resolution)->toBe(R::Blocked)->and($result->completed)->toBeNull()->and($result->reasons)->toContain('DIFFERENT_SOURCE_RECORD');
});

it('rejects malformed observation identities', function () {
    expect(fn () => new ObservedCell('invalid', 'sheet-1', new CellObservation(2, 1, 'PC1')))->toThrow(InvalidArgumentException::class);
});

it('keeps real Wald House No and Sales Plot ambiguity unresolved with both candidates', function () {
    $rows = [1 => [1 => 'House No.', 2 => 'Sales Plot']];
    for ($row = 1; $row <= 10; $row++) {
        $rows[$row + 1] = [1 => sprintf('H%03d', $row), 2 => sprintf('P%03d', $row)];
    }
    $values = new ValueProfiler;
    $profiler = new WorkbookProfiler(new WorkbookSourceFactory,
        new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values));
    $profile = $profiler->profile(WaldFixtures::xlsx([['rows' => $rows]]), 'xlsx');
    $reasoning = (new ReasoningEngine)->reason($profile);
    $data = $reasoning->toArray();
    foreach ([1, 2] as $column) {
        $candidate = array_values(array_filter($data['hypotheses'], fn ($c) => $c['hypothesis']['target']['column'] === $column && $c['hypothesis']['definition']['key'] === 'identifier_like'))[0];
        $observation = new ObservedCell($data['manifest']['source_checksum'], $candidate['hypothesis']['target']['sheet_id'],
            new CellObservation(2, $column, $rows[2][$column]));
        $result = (new SemanticAdapter)->interpret($observation, $reasoning, $candidate['hypothesis']['id']);
        expect($result->classification)->toBe(C::Ambiguous)->and($result->resolution)->toBe(R::Blocked)->and($result->value)->toBeNull()
            ->and($result->reasons)->toContain('WALD_CLARIFICATION_REQUIRED')
            ->and($result->evidence['wald_candidate']['evidence'])->not->toBeEmpty();
        $ties = array_values(array_filter($result->evidence['wald_clarifications'], fn ($c) => $c['reason'] === 'exclusive_role_near_tie'));
        expect($ties[0]['competing_candidates'])->toHaveCount(2);
        expect((new SemanticAdapter)->interpret($observation, $reasoning, $candidate['hypothesis']['id'])->jsonSerialize())->toBe($result->jsonSerialize());
    }
});
