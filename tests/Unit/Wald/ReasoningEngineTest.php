<?php

use App\Wald\Contracts\Reasoning\ConstraintFinding;
use App\Wald\Contracts\Reasoning\Evidence;
use App\Wald\Contracts\Reasoning\EvidenceDirection;
use App\Wald\Contracts\Reasoning\EvidenceTrust;
use App\Wald\Contracts\Reasoning\HypothesisDefinition;
use App\Wald\Contracts\Reasoning\InferenceRule;
use App\Wald\Contracts\Reasoning\ReasoningConstraint;
use App\Wald\Contracts\Reasoning\RiskClass;
use App\Wald\Contracts\Reasoning\RuleApplicability;
use App\Wald\Contracts\Reasoning\RuleContext;
use App\Wald\Contracts\Reasoning\RuleDefinition;
use App\Wald\Contracts\Reasoning\RulePhase;
use App\Wald\Contracts\WorkbookProfile;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\Reasoning\ConfidencePolicy;
use App\Wald\Services\Reasoning\ConfidenceResolver;
use App\Wald\Services\Reasoning\ConstraintEngine;
use App\Wald\Services\Reasoning\EvidenceScorer;
use App\Wald\Services\Reasoning\ExclusiveRoleConstraint;
use App\Wald\Services\Reasoning\HypothesisCatalog;
use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\Reasoning\ReasoningProblem;
use App\Wald\Services\Reasoning\RuleRegistry;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures as Fixtures;

function reasoningProfile(array $rows, array $options = []): WorkbookProfile
{
    $values = new ValueProfiler;
    $profiler = new WorkbookProfiler(new WorkbookSourceFactory, new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values));

    return $profiler->profile(Fixtures::xlsx([['rows' => $rows, ...$options]]), 'xlsx');
}

function shapeRows(string $header = 'Category', ?callable $value = null): array
{
    $rows = [1 => [1 => 'Reference', 2 => $header]];
    for ($i = 1; $i <= 10; $i++) {
        $rows[$i + 1] = [1 => sprintf('A%02d', $i), 2 => $value ? $value($i) : ($i % 2 === 0 ? 'Alpha' : 'Beta')];
    }

    return $rows;
}

function reasonCandidate(array $result, int $column, string $shape): array
{
    return array_values(array_filter($result['hypotheses'], fn ($candidate) => $candidate['hypothesis']['target']['column'] === $column && $candidate['hypothesis']['definition']['key'] === $shape))[0];
}

function scoreCandidate(string $id, int $score, RiskClass $risk = RiskClass::Descriptive, ?string $exclusive = null): array
{
    return ['hypothesis' => ['id' => $id, 'target' => ['id' => $id, 'region_id' => 'r1'], 'definition' => ['risk' => $risk->value, 'exclusive_within_region' => $exclusive]],
        'scoring' => ['score' => $score, 'support' => $score, 'penalty' => 0, 'veto' => false, 'supporting_families' => 3], 'constraints' => [], 'critical_full_validation' => false];
}

function stubWaldRule(RuleDefinition $definition, ?Closure $evaluate = null, ?RuleApplicability $applicability = null): InferenceRule
{
    return new class($definition, $evaluate, $applicability) implements InferenceRule
    {
        public function __construct(private RuleDefinition $rule, private ?Closure $run, private ?RuleApplicability $applies) {}

        public function definition(): RuleDefinition
        {
            return $this->rule;
        }

        public function applicability(RuleContext $context): RuleApplicability
        {
            return $this->applies ?? RuleApplicability::applicable();
        }

        public function evaluate(RuleContext $context): array
        {
            return $this->run === null ? [] : ($this->run)($context);
        }
    };
}

afterEach(fn () => Fixtures::cleanup());

it('combines independent evidence for a clear identifier with neutral lineage and explanation', function () {
    $profile = reasoningProfile(shapeRows());
    $result = (new ReasoningEngine)->reason($profile)->toArray();
    $candidate = reasonCandidate($result, 1, 'identifier_like');
    expect($result['schema'])->toBe('wald.reasoning.v1')->and($result['ready_for_staging'])->toBeFalse()
        ->and($candidate['scoring']['score'])->toBe(70)->and($candidate['decision'])->toBe('accepted')
        ->and($candidate['confidence']['band'])->toBe('high_confidence')
        ->and($candidate['confidence']['is_probability'])->toBeFalse()
        ->and(array_column($candidate['evidence'], 'rule_key'))->toContain('wald.generic.header_tokens', 'wald.generic.uniqueness', 'wald.generic.pattern_consistency', 'wald.generic.null_density')
        ->and($candidate['hypothesis']['target']['source_refs'][0]['source_checksum'])->toBe($profile->toArray()['source_checksum'])
        ->and($candidate['explanation']['why_wald_thinks_this'][0]['text'])->toBe('The header contains a generic term for this shape.')
        ->and($result['manifest']['dictionary_versions'])->toBe([]);
});

it('keeps two equally plausible identifier columns ambiguous with no arbitrary winner', function () {
    $result = (new ReasoningEngine)->reason(reasoningProfile(shapeRows('Identifier', fn ($i) => 'B'.$i)))->toArray();
    foreach ([1, 2] as $column) {
        expect(reasonCandidate($result, $column, 'identifier_like')['decision'])->toBe('clarification_required');
    }
    $ambiguity = array_values(array_filter($result['clarifications'], fn ($item) => $item['reason'] === 'exclusive_role_near_tie'))[0];
    expect($ambiguity['margin'])->toBe(0)->and($ambiguity['leading_candidate'])->toBeNull()
        ->and($ambiguity['competing_candidates'])->toHaveCount(2)
        ->and($ambiguity['competing_candidates'][0]['samples'])->toHaveCount(5)
        ->and($ambiguity['question_type_hint'])->toBe('choose_candidate');
});

it('ranks dates quantities categories and free text using generic shapes', function (string $header, Closure $values, string $shape, string $expected) {
    $result = (new ReasoningEngine)->reason(reasoningProfile(shapeRows($header, $values)))->toArray();
    $candidate = reasonCandidate($result, 2, $shape);
    expect($candidate['decision'])->toBe($expected)->and($candidate['scoring']['score'])->toBeGreaterThanOrEqual(50)
        ->and($candidate['evidence'])->not->toBeEmpty()
        ->and($candidate['explanation']['why_wald_thinks_this'])->not->toBeEmpty();
})->with([
    ['Date', fn ($i) => '2026-09-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'date_like', 'accepted'],
    ['When', fn ($i) => '25/09/2026', 'date_like', 'accepted'],
    ['Date', fn ($i) => ['value' => 46000 + $i, 'style' => 2], 'date_like', 'accepted'],
    ['Date', fn ($i) => ['value' => '2026-09-01', 'type' => 'd'], 'date_like', 'accepted'],
    ['Quantity', fn ($i) => $i % 2 === 0 ? 2 : 4, 'quantity_like', 'accepted'],
    ['Amount', fn ($i) => $i / 2 + 0.25, 'quantity_like', 'accepted'],
    ['Category', fn ($i) => $i % 2 === 0 ? 'Alpha' : 'Beta', 'category_like', 'accepted'],
    ['Notes', fn ($i) => 'This is a long descriptive sentence explaining an observation with variation '.$i.'.', 'free_text_like', 'accepted'],
]);

it('does not turn numeric identifiers or percentages into confident quantities', function () {
    $rows = shapeRows('Quantity', fn ($i) => $i);
    for ($row = 2; $row <= 11; $row++) {
        $rows[$row][1] = $row;
    }
    $result = (new ReasoningEngine)->reason(reasoningProfile($rows))->toArray();
    expect(reasonCandidate($result, 1, 'identifier_like')['decision'])->toBe('accepted')
        ->and(reasonCandidate($result, 2, 'quantity_like')['decision'])->toBe('clarification_required');
    $result = (new ReasoningEngine)->reason(reasoningProfile(shapeRows('Quantity', fn ($i) => '50%')))->toArray();
    expect(reasonCandidate($result, 2, 'quantity_like')['decision'])->toBe('contradiction')
        ->and(reasonCandidate($result, 2, 'quantity_like')['traits']['percentage_like'])->toBeTrue();
});

it('reduces date certainty when day month order is ambiguous', function () {
    $result = (new ReasoningEngine)->reason(reasoningProfile(shapeRows('Date', fn ($i) => '01/02/26')))->toArray();
    $candidate = reasonCandidate($result, 2, 'date_like');
    expect($candidate['decision'])->toBe('clarification_required')->and($candidate['scoring']['penalty'])->toBe(20)
        ->and(array_column($candidate['evidence'], 'explanation_code'))->toContain('date_ambiguity');
});

it('preserves decisive contradiction when a date header contains prose', function () {
    $result = (new ReasoningEngine)->reason(reasoningProfile(shapeRows('Date', fn ($i) => 'A detailed narrative with the number 2026 in the middle and no actual date.')))->toArray();
    $candidate = reasonCandidate($result, 2, 'date_like');
    expect($candidate['decision'])->toBe('contradiction')->and($candidate['scoring']['support'])->toBeGreaterThanOrEqual(30)
        ->and($candidate['scoring']['veto'])->toBeTrue()
        ->and($candidate['explanation']['what_makes_wald_uncertain'])->not->toBeEmpty();
});

it('requires confirmation for hidden formula derived and mostly blank candidates', function (string $case) {
    $rows = shapeRows();
    $options = [];
    if ($case === 'hidden') {
        $options = ['hidden_columns' => [1]];
    } elseif ($case === 'formula') {
        for ($row = 2; $row <= 11; $row++) {
            $rows[$row][1] = ['formula' => 'ROW()', 'value' => $row, 'type' => 'n'];
        }
    } else {
        $rows[1][3] = 'Amount';
        for ($row = 2; $row <= 11; $row++) {
            $rows[$row][3] = $row;
        }
        for ($row = 5; $row <= 11; $row++) {
            $rows[$row][1] = '';
        }
    }
    $candidate = reasonCandidate((new ReasoningEngine)->reason(reasoningProfile($rows, $options))->toArray(), 1, 'identifier_like');
    expect($candidate['decision'])->not->toBe('accepted')->and($candidate['scoring']['penalty'])->toBeGreaterThan(0);
})->with(['hidden', 'formula', 'blank']);

it('includes repeated block evidence without counting correlated layout twice', function () {
    $rows = shapeRows() + Fixtures::move(shapeRows(), 13);
    $candidate = reasonCandidate((new ReasoningEngine)->reason(reasoningProfile($rows))->toArray(), 1, 'identifier_like');
    expect(array_column($candidate['evidence'], 'rule_key'))->toContain('wald.generic.repeated_block')
        ->and($candidate['scoring']['families']['surrounding_structure']['support'])->toBe(15)
        ->and(array_column($candidate['scoring']['evidence_accounting'], 'suppression'))->toContain('correlated_or_informational');
});

it('is stable under moved headers inserted blanks titles and column reordering', function (string $mutation) {
    $rows = shapeRows();
    $column = 1;
    if ($mutation === 'move') {
        $rows = Fixtures::move($rows, 4, 2);
        $column = 3;
    } elseif ($mutation === 'blank_column') {
        $rows = Fixtures::insertColumn($rows, 1);
        $column = 2;
    } elseif ($mutation === 'title') {
        $rows = [1 => [1 => 'Example report']] + Fixtures::move($rows, 3);
    } else {
        $rows = Fixtures::reorder($rows, [2, 1]);
        $column = 2;
    }
    $candidate = reasonCandidate((new ReasoningEngine)->reason(reasoningProfile($rows))->toArray(), $column, 'identifier_like');
    expect($candidate['scoring']['score'])->toBe(70)->and($candidate['decision'])->toBe('accepted')
        ->and(array_column($candidate['evidence'], 'rule_key'))->toContain('wald.generic.uniqueness');
})->with(['move', 'blank_column', 'title', 'reorder']);

it('cannot overcome duplicate identifiers with an unchanged strong heading', function () {
    $rows = shapeRows();
    $before = reasonCandidate((new ReasoningEngine)->reason(reasoningProfile($rows))->toArray(), 1, 'identifier_like');
    for ($row = 2; $row <= 11; $row++) {
        $rows[$row][1] = 'A'.($row % 2);
    }
    $after = reasonCandidate((new ReasoningEngine)->reason(reasoningProfile($rows))->toArray(), 1, 'identifier_like');
    expect($before['decision'])->toBe('accepted')->and($after['decision'])->toBe('contradiction')
        ->and($after['scoring']['score'])->toBeLessThan($before['scoring']['score'])
        ->and(array_column($after['evidence'], 'explanation_code'))->toContain('identifier_repetition');
});

it('is deterministic including registry insertion order and complete diagnostic output', function () {
    $profile = reasoningProfile(shapeRows());
    $engine = new ReasoningEngine;
    $a = $engine->reason($profile)->toArray();
    $b = (new ReasoningEngine(new RuleRegistry(array_reverse((new RuleRegistry)->all()))))->reason($profile)->toArray();
    expect($engine->reason($profile)->toArray())->toBe($a)->and($b)->toBe($a)
        ->and($a['diagnostics']['phases'])->toBe(['Structure', 'Candidates', 'Evidence', 'Contradiction', 'Constraints', 'Confidence']);
});

it('caps correlated evidence and preserves contradiction penalties and reliability', function () {
    $items = [];
    foreach (['one', 'two', 'three'] as $key) {
        $items[] = new Evidence('wald.test.'.$key, '1', 'candidate', EvidenceDirection::Supporting, 30, 'heading', 'same_signal', 'header', 'Header evidence.', [], []);
    }
    $items[] = new Evidence('wald.test.contra', '1', 'candidate', EvidenceDirection::Contradicting, 20, 'value_distribution', 'duplicates', 'duplicate', 'Repeated values.', [], []);
    $scoring = (new EvidenceScorer)->score($items, ['wald.test.one' => 100, 'wald.test.two' => 100, 'wald.test.three' => 50, 'wald.test.contra' => 100]);
    expect($scoring['support'])->toBe(30)->and($scoring['penalty'])->toBe(20)->and($scoring['score'])->toBe(10)
        ->and($scoring['supporting_families'])->toBe(1)
        ->and(count(array_filter($scoring['evidence_accounting'], fn ($row) => $row['suppression'] !== null)))->toBe(2);
});

it('prevents a single uncorrelated family from exceeding its cap', function () {
    $items = [new Evidence('wald.test.one', '1', 'c', EvidenceDirection::Supporting, 100, 'heading', 'one', 'one', 'One.', [], []), new Evidence('wald.test.two', '1', 'c', EvidenceDirection::Supporting, 100, 'heading', 'two', 'two', 'Two.', [], [])];
    expect((new EvidenceScorer)->score($items, ['wald.test.one' => 100, 'wald.test.two' => 100])['score'])->toBe(30);
});

it('only clears an exclusive near tie when the configured margin is reached', function (int $second, string $outcome) {
    $candidates = ['a' => scoreCandidate('a', 70, exclusive: 'primary'), 'b' => scoreCandidate('b', $second, exclusive: 'primary')];
    $findings = (new ExclusiveRoleConstraint)->evaluate($candidates, new ConfidencePolicy);
    expect($findings[0]->outcome)->toBe($outcome);
})->with([[70, 'clarification'], [65, 'clarification'], [61, 'clarification'], [60, 'pass']]);

it('uses WALD01 risk thresholds without inventing confidence percentages', function (RiskClass $risk, int $score, bool $validated, string $expected) {
    $candidate = scoreCandidate('c', $score, $risk);
    $candidate['critical_full_validation'] = $validated;
    $result = (new ConfidenceResolver)->resolve($candidate, 30, true, new ConfidencePolicy);
    expect($result['decision'])->toBe($expected)->and($result['confidence']['is_probability'])->toBeFalse();
})->with([
    [RiskClass::Critical, 95, false, 'clarification_required'], [RiskClass::Critical, 95, true, 'accepted'],
    [RiskClass::Material, 84, false, 'clarification_required'], [RiskClass::Material, 85, false, 'accepted'],
    [RiskClass::Descriptive, 70, false, 'accepted'], [RiskClass::Descriptive, 60, false, 'accepted_reviewable'], [RiskClass::Descriptive, 20, false, 'insufficient_evidence'],
]);

it('rejects invalid evidence and unavailable trust claims', function () {
    expect(fn () => new Evidence('wald.test.x', '1', 'c', EvidenceDirection::Supporting, 101, 'heading', 'x', 'x', 'X', [], []))->toThrow(ReasoningProblem::class);
    $item = new Evidence('wald.test.x', '1', 'c', EvidenceDirection::Supporting, 20, 'confirmed_profile', 'x', 'x', 'X', [], [], trust: EvidenceTrust::Profile);
    expect(fn () => (new EvidenceScorer)->score([$item], ['wald.test.x' => 100]))->toThrow(ReasoningProblem::class);
});

it('validates registry identities phases and dependencies before execution', function (string $case) {
    $base = stubWaldRule(new RuleDefinition('wald.test.base', '1', RulePhase::Evidence, 'Base'));
    $rules = match ($case) {
        'duplicate' => [$base, $base],
        'missing' => [stubWaldRule(new RuleDefinition('wald.test.one', '1', RulePhase::Evidence, 'One', dependsOn: ['missing']))],
        'cycle' => [stubWaldRule(new RuleDefinition('wald.test.one', '1', RulePhase::Evidence, 'One', dependsOn: ['wald.test.two'])), stubWaldRule(new RuleDefinition('wald.test.two', '1', RulePhase::Evidence, 'Two', dependsOn: ['wald.test.one']))],
        'phase' => [stubWaldRule(new RuleDefinition('wald.test.one', '1', RulePhase::Confidence, 'Invalid phase'))],
        'weight' => [stubWaldRule(new RuleDefinition('wald.test.one', '1', RulePhase::Evidence, 'Invalid reliability', reliability: 101))],
    };
    expect(fn () => new RuleRegistry($rules))->toThrow(ReasoningProblem::class);
})->with(['duplicate', 'missing', 'cycle', 'phase', 'weight']);

it('distinguishes not applicable from missing prerequisites without negative evidence', function () {
    $rules = [stubWaldRule(new RuleDefinition('wald.test.na', '1', RulePhase::Evidence, 'Not applicable'), applicability: RuleApplicability::notApplicable('wrong_shape')), stubWaldRule(new RuleDefinition('wald.test.missing', '1', RulePhase::Evidence, 'Missing', ['column.nonexistent']))];
    $result = (new ReasoningEngine(new RuleRegistry($rules)))->reason(reasoningProfile(shapeRows()))->toArray();
    expect(array_column($result['diagnostics']['rule_evaluations'], 'state'))->toContain('not_applicable', 'cannot_evaluate')
        ->and($result['hypotheses'][0]['scoring']['penalty'])->toBe(0)
        ->and($result['hypotheses'][0]['evidence'])->toBe([]);
});

it('continues an isolated optional rule failure but refuses systematic and critical failures', function (string $mode) {
    $rule = stubWaldRule(new RuleDefinition('wald.test.failure', '1', RulePhase::Evidence, 'Failure', critical: $mode === 'critical'), function ($context) use ($mode) {
        if ($mode !== 'isolated' || ($context->hypothesis->definition->key === 'identifier_like' && $context->hypothesis->target['column'] === 1)) {
            throw new RuntimeException('Sensitive local path should never be exposed.');
        }

        return [];
    });
    $engine = new ReasoningEngine(new RuleRegistry([...(new RuleRegistry)->all(), $rule]));
    $profile = reasoningProfile(shapeRows());
    if ($mode === 'isolated') {
        $result = $engine->reason($profile)->toArray();
        expect(reasonCandidate($result, 1, 'identifier_like')['decision'])->toBe('clarification_required')
            ->and($result['diagnostics']['warnings'][0]['code'])->toBe('optional_rule_failed')
            ->and(json_encode($result))->not->toContain('Sensitive local path');
    } else {
        expect(fn () => $engine->reason($profile))->toThrow(ReasoningProblem::class);
    }
})->with(['isolated', 'systematic', 'critical']);

it('rejects invalid or incomplete structural input', function () {
    $data = reasoningProfile(shapeRows())->toArray();
    $data['complete'] = false;
    expect(fn () => (new ReasoningEngine)->reason(new WorkbookProfile($data)))->toThrow(ReasoningProblem::class);
});

it('supports bounded workbook constraint adjustments and fails safely on invalid results', function () {
    $constraint = new class implements ReasoningConstraint
    {
        public function definition(): array
        {
            return ['key' => 'wald.test.workbook', 'version' => '1', 'scope' => 'workbook'];
        }

        public function evaluate(array $candidates, ConfidencePolicy $policy): array
        {
            return [new ConstraintFinding('wald.test.workbook', '1', 'adjustment', ['c'], 'corroboration_missing', -20)];
        }
    };
    $result = (new ConstraintEngine([$constraint]))->apply(['c' => scoreCandidate('c', 70)], new ConfidencePolicy);
    expect($result['candidates']['c']['scoring']['score'])->toBe(50)->and($result['trace'][0]['adjustment'])->toBe(-20);
    expect(fn () => new ConstraintFinding('wald.test.x', '1', 'adjustment', ['c'], 'bad', 20))->toThrow(ReasoningProblem::class);
});

it('keeps traits orthogonal while shape roles compete and never emits SiteApp mappings', function () {
    $result = (new ReasoningEngine)->reason(reasoningProfile(shapeRows('Date', fn ($i) => '2026-09-01')))->toArray();
    expect($result['hypotheses'][0]['hypothesis']['definition']['kind'])->toBe('shape_role')
        ->and(reasonCandidate($result, 2, 'date_like')['traits']['date_like'])->toBeTrue();
    foreach ($result['targets'] as $target) {
        $accepted = array_filter($result['hypotheses'], fn ($candidate) => $candidate['hypothesis']['target']['id'] === $target['target_id'] && $candidate['decision'] === 'accepted');
        expect(count($accepted))->toBeLessThanOrEqual(1);
    }
    foreach (['plot_id', 'house_type_id', 'workflow_stage_id', 'PC1', 'CM1'] as $forbidden) {
        expect(json_encode($result))->not->toContain($forbidden);
    }
});

it('keeps competing column roles unresolved until the exact margin threshold', function (int $adjustment, string $decision) {
    $catalog = new HypothesisCatalog([
        new HypothesisDefinition('shape_alpha', 'Shape Alpha', ['reference']),
        new HypothesisDefinition('shape_beta', 'Shape Beta', ['reference']),
    ]);
    $rule = stubWaldRule(new RuleDefinition('wald.test.shape_support', '1', RulePhase::Evidence, 'Test shape evidence'), fn ($context) => [
        new Evidence('wald.test.shape_support', '1', $context->hypothesis->id, EvidenceDirection::Supporting, 25, 'value_distribution', 'test_shape', 'shape', 'Test shape evidence.', [], $context->hypothesis->target['source_refs']),
    ]);
    $constraint = new class($adjustment) implements ReasoningConstraint
    {
        public function __construct(private int $adjustment) {}

        public function definition(): array
        {
            return ['key' => 'wald.test.margin', 'version' => '1', 'scope' => 'column'];
        }

        public function evaluate(array $candidates, ConfidencePolicy $policy): array
        {
            $ids = array_keys(array_filter($candidates, fn ($candidate) => $candidate['hypothesis']['definition']['key'] === 'shape_beta'));

            return [new ConstraintFinding('wald.test.margin', '1', 'adjustment', $ids, 'test_counter_context', -$this->adjustment)];
        }
    };
    $result = (new ReasoningEngine(new RuleRegistry([...(new RuleRegistry)->all(), $rule]), $catalog, constraints: new ConstraintEngine([$constraint])))->reason(reasoningProfile(shapeRows()))->toArray();
    $candidate = reasonCandidate($result, 1, 'shape_alpha');
    expect($candidate['scoring']['score'])->toBe(70)->and($candidate['decision'])->toBe($decision)
        ->and($candidate['confidence']['margin'])->toBe($adjustment)->and($candidate['scoring']['veto'])->toBeFalse();
    if ($adjustment < 10) {
        $ambiguity = array_values(array_filter($result['clarifications'], fn ($item) => $item['reason'] === 'competing_column_shapes'))[0];
        expect($ambiguity['competing_candidates'])->toHaveCount(2)
            ->and($ambiguity['competing_candidates'][0]['confidence']['band'])->toBe('needs_confirmation')
            ->and($ambiguity['competing_candidates'][0]['evidence_summary']['families'])->toBe(3);
    }
})->with([[0, 'clarification_required'], [9, 'clarification_required'], [10, 'accepted']]);

it('resolves exclusivity using adjusted scores not the original ranking', function () {
    $constraint = new class implements ReasoningConstraint
    {
        public function definition(): array
        {
            return ['key' => 'wald.test.reduce', 'version' => '1', 'scope' => 'hypothesis'];
        }

        public function evaluate(array $candidates, ConfidencePolicy $policy): array
        {
            return [new ConstraintFinding('wald.test.reduce', '1', 'adjustment', ['alpha'], 'less_reliable', -20)];
        }
    };
    $result = (new ConstraintEngine([new ExclusiveRoleConstraint, $constraint]))->apply(['alpha' => scoreCandidate('alpha', 70, exclusive: 'primary'), 'beta' => scoreCandidate('beta', 65, exclusive: 'primary')], new ConfidencePolicy);
    expect(array_column($result['candidates']['alpha']['constraints'], 'outcome'))->toContain('rejected')
        ->and(array_column($result['candidates']['beta']['constraints'], 'outcome'))->toBe(['pass']);
});

it('refuses to accept high raw strength without independent evidence families', function () {
    $candidate = scoreCandidate('example', 95);
    $candidate['scoring']['supporting_families'] = 1;
    $result = (new ConfidenceResolver)->resolve($candidate, 30, true, new ConfidencePolicy);
    expect($result['decision'])->toBe('clarification_required')
        ->and($result['confidence']['reasons'])->toContain('insufficient_evidence_diversity');
});

it('retains informational observations without increasing or reducing confidence', function () {
    $item = new Evidence('wald.test.info', '1', 'example', EvidenceDirection::Informational, 0, 'format_metadata', 'note', 'note', 'Context only.', [], []);
    $score = (new EvidenceScorer)->score([$item], ['wald.test.info' => 100]);
    expect($score['score'])->toBe(0)->and($score['penalty'])->toBe(0)->and($score['supporting_families'])->toBe(0)
        ->and($score['evidence_accounting'][0]['counted_before_family_cap'])->toBeFalse();
});

it('reports malformed nested profiles as safe structured errors', function (string $case) {
    $data = reasoningProfile(shapeRows())->toArray();
    if ($case === 'sheets') {
        unset($data['sheets']);
    } elseif ($case === 'types') {
        unset($data['sheets'][0]['regions'][0]['column_profiles'][0]['types']);
    } else {
        $data['sheets'][0]['regions'][0]['column_profiles'][0]['identifier']['count'] = -1;
    }
    expect(fn () => (new ReasoningEngine)->reason(new WorkbookProfile($data)))->toThrow(ReasoningProblem::class);
})->with(['sheets', 'types', 'count']);

it('bounds eligible candidates without silently truncating alternatives', function () {
    $definitions = [];
    for ($i = 1; $i <= 6; $i++) {
        $definitions[] = new HypothesisDefinition('shape_'.$i, 'Shape '.$i, []);
    }
    $catalog = new HypothesisCatalog($definitions);
    expect(fn () => $catalog->forTraits([]))->toThrow(ReasoningProblem::class);
    $catalog = new HypothesisCatalog([new HypothesisDefinition('numeric_shape', 'Numeric', [], eligibleTraits: ['numeric']), new HypothesisDefinition('date_shape', 'Date', [], eligibleTraits: ['date_like'])]);
    expect(array_map(fn ($definition) => $definition->key, $catalog->forTraits(['numeric' => true])))->toBe(['numeric_shape']);
});

it('pins rule versions and evidence IDs in the analysis manifest', function () {
    $profile = reasoningProfile(shapeRows());
    $make = fn ($version) => stubWaldRule(new RuleDefinition('wald.test.versioned', $version, RulePhase::Evidence, 'Version test'), fn ($context) => [new Evidence('wald.test.versioned', $version, $context->hypothesis->id, EvidenceDirection::Supporting, 10, 'heading', 'header', 'header', 'Test heading.', [], $context->hypothesis->target['source_refs'])]);
    $first = (new ReasoningEngine(new RuleRegistry([$make('1')])))->reason($profile)->toArray();
    $second = (new ReasoningEngine(new RuleRegistry([$make('2')])))->reason($profile)->toArray();
    expect($first['manifest_hash'])->not->toBe($second['manifest_hash'])
        ->and($first['hypotheses'][0]['evidence'][0]['evidence_id'])->not->toBe($second['hypotheses'][0]['evidence'][0]['evidence_id']);
});

it('honours explicit dependencies ahead of priority and records disabled rules', function () {
    $base = stubWaldRule(new RuleDefinition('wald.test.base', '1', RulePhase::Evidence, 'Base', priority: 200));
    $dependent = stubWaldRule(new RuleDefinition('wald.test.dependent', '1', RulePhase::Evidence, 'Dependent', dependsOn: ['wald.test.base'], priority: 1));
    $disabled = stubWaldRule(new RuleDefinition('wald.test.disabled', '1', RulePhase::Evidence, 'Disabled', enabled: false), fn () => throw new RuntimeException('Must not run'));
    $result = (new ReasoningEngine(new RuleRegistry([$dependent, $disabled, $base])))->reason(reasoningProfile(shapeRows()))->toArray();
    $executed = array_values(array_filter($result['diagnostics']['rule_evaluations'], fn ($entry) => $entry['state'] === 'executed'));
    expect(array_slice(array_column($executed, 'rule_key'), 0, 2))->toBe(['wald.test.base', 'wald.test.dependent'])
        ->and(array_column($result['diagnostics']['rule_evaluations'], 'state'))->toContain('disabled');
});

it('rejects evidence that substitutes unrelated source provenance', function () {
    $rule = stubWaldRule(new RuleDefinition('wald.test.bad_source', '1', RulePhase::Evidence, 'Bad source'), fn ($context) => [new Evidence('wald.test.bad_source', '1', $context->hypothesis->id, EvidenceDirection::Supporting, 20, 'heading', 'header', 'header', 'Test.', [], [['sheet_id' => 'other']])]);
    expect(fn () => (new ReasoningEngine(new RuleRegistry([$rule])))->reason(reasoningProfile(shapeRows())))->toThrow(ReasoningProblem::class);
});
