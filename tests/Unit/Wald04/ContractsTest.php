<?php

use App\SourceImport\Knowledge\AnalysisSnapshot;
use App\SourceImport\Knowledge\Canonical;
use App\SourceImport\Knowledge\Compatibility;
use App\SourceImport\Knowledge\KnowledgeIdentity;
use App\SourceImport\Knowledge\KnowledgeScope;
use App\SourceImport\Knowledge\ProfileMatcher;
use App\SourceImport\Knowledge\RetentionPolicy;
use Carbon\CarbonImmutable;
use Tests\Support\Wald04Fixtures as F;
use Tests\Support\WaldFixtures;

afterEach(fn () => WaldFixtures::cleanup());

it('pins corrected executable core confidence dictionary and schema identities', function () {
    $identity = new KnowledgeIdentity;
    $pins = $identity->current();
    expect($pins['fingerprint'])->toBe(KnowledgeIdentity::FINGERPRINT)
        ->and($identity->compatible($pins))->toBe(Compatibility::Exact)
        ->and($pins['accepted_baseline'])->toBe('a80ce7d14206cf3f3a9343448d406f01ae927b88');
});

it('fails closed when any compatibility pin changes', function ($key) {
    $identity = new KnowledgeIdentity;
    $pins = $identity->current();
    $pins[$key] = 'changed';
    expect($identity->compatible($pins))->toBe($key === 'fingerprint' ? Compatibility::Incompatible : Compatibility::Stale);
})->with(['dictionary', 'fingerprint', 'core', 'reader_adapters', 'schema', 'signature', 'matcher', 'selector', 'semantic_executable', 'accepted_baseline', 'policy']);

it('canonicalizes associative order but preserves lists punctuation and multiplicity', function () {
    expect(Canonical::hash(['b' => 2, 'a' => ['y' => 2, 'x' => 1]]))->toBe(Canonical::hash(['a' => ['x' => 1, 'y' => 2], 'b' => 2]))
        ->and(Canonical::hash(['a', 'b']))->not->toBe(Canonical::hash(['b', 'a']))
        ->and(AnalysisSnapshot::token('  Sales   Plot! '))->toBe('sales plot!');
});

it('rejects malformed scope and oversized plain-data envelopes', function () {
    expect(fn () => new KnowledgeScope(0, 1, 'source', 'family'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new KnowledgeScope(1, 1, '../source', 'family'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Canonical::json(str_repeat('x', 65537), 65536))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Canonical::json(new stdClass))->toThrow(InvalidArgumentException::class);
});

it('classifies reordered and materially altered structures without applying them', function ($headers, $expected) {
    $original = F::snapshot()->data;
    $selection = $original['questions']['structure:plot_reference']['candidates'][0];
    $definition = ['pins' => $original['pins'], 'descriptor' => $original['tables'][$selection['table']]['descriptor'],
        'selection' => ['role' => 'plot_reference', 'selector' => 'plot']];
    $fresh = F::snapshot($headers)->data;
    $result = (new ProfileMatcher)->evaluate($definition, $fresh);
    expect($result['compatibility'])->toBe($expected)->and($result['applied'])->toBeFalse();
})->with([
    [['VS', 'Call Type', 'Plot'], 'COMPATIBLE_WITH_REVIEW'],
    [['Plot', 'Call Type', 'BF'], 'INCOMPATIBLE'],
    [['House No.', 'Sales Plot', 'VS'], 'COMPATIBLE_WITH_REVIEW'],
    [['Unknown Plot Name', 'Call Type', 'VS'], 'INCOMPATIBLE'],
    [['Sales Plot', 'Call Type', 'VS'], 'COMPATIBLE_WITH_REVIEW'],
]);

it('rejects fresh contradictions without altering remembered structure', function ($safetyFlag) {
    $fresh = F::snapshot()->data;
    $selection = $fresh['questions']['structure:plot_reference']['candidates'][0];
    $definition = ['pins' => $fresh['pins'], 'descriptor' => $fresh['tables'][$selection['table']]['descriptor'],
        'selection' => ['role' => 'plot_reference', 'selector' => 'plot']];
    $before = Canonical::hash($definition);
    $fresh['tables'][$selection['table']]['column_safety'][$selection['column']][$safetyFlag] = true;
    expect((new ProfileMatcher)->evaluate($definition, $fresh)['compatibility'])->toBe('INCOMPATIBLE')
        ->and(Canonical::hash($definition))->toBe($before);
})->with(['error_or_formula', 'negative_quantity', 'date_or_percent', 'empty']);

it('retains period metadata and holds without authorising automatic disposal', function ($kind, $expected) {
    $policy = new RetentionPolicy;
    $at = CarbonImmutable::parse('2026-09-08 12:00:00 UTC');
    $due = $policy->due($kind, $at);
    expect($due->format('Y-m-d H:i'))->toBe($expected)
        ->and($policy->eligible($due, $due, false, false))->toBeTrue()
        ->and($policy->eligible($due, $due, true, false))->toBeFalse()
        ->and($policy->eligible($due, $due, false, true))->toBeFalse()
        ->and($policy->eligible($due, $due->subSecond(), false, false))->toBeFalse()
        ->and($policy->automaticDisposalEnabled())->toBeFalse();
})->with([
    ['workbook', '2026-10-08 12:00'], ['observation', '2026-09-15 12:00'],
    ['preview', '2026-09-09 12:00'], ['preview_payload', '2026-09-15 12:00'],
    ['profile_review', '2027-09-08 12:00'], ['knowledge_history', '2028-09-08 12:00'],
]);
