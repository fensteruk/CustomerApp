<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\Ambiguity;
use App\Wald\Contracts\Reasoning\Evidence;
use App\Wald\Contracts\Reasoning\ReasoningResult;
use App\Wald\Contracts\Reasoning\RiskClass;
use App\Wald\Contracts\Reasoning\RulePhase;
use App\Wald\Contracts\WorkbookProfile;
use App\Wald\Services\AnalysisBudget;

final class ReasoningEngine
{
    public const VERSION = 'wald-0.3.0';

    public const RULESET_VERSION = 'wald.generic-rules.v1';

    public function __construct(
        private readonly RuleRegistry $registry = new RuleRegistry,
        private readonly HypothesisCatalog $catalog = new HypothesisCatalog,
        private readonly ProfileContextFactory $contexts = new ProfileContextFactory,
        private readonly EvidenceScorer $scorer = new EvidenceScorer,
        private readonly ConstraintEngine $constraints = new ConstraintEngine,
        private readonly ConfidencePolicy $policy = new ConfidencePolicy,
        private readonly ConfidenceResolver $confidence = new ConfidenceResolver,
        private readonly ExplanationBuilder $explanations = new ExplanationBuilder,
    ) {}

    public function reason(WorkbookProfile $profile): ReasoningResult
    {
        $budget = new AnalysisBudget;
        $candidates = $trace = $warnings = $failures = [];
        $executions = 0;
        $reliability = [];
        foreach ($this->registry->all() as $rule) {
            $reliability[$rule->definition()->key] = $rule->definition()->reliability;
        }
        foreach ($this->contexts->contexts($profile, $this->catalog) as $context) {
            $evidence = [];
            $statuses = [];
            $incomplete = false;
            foreach ($this->registry->all() as $rule) {
                $definition = $rule->definition();
                if (++$executions > 60000) {
                    throw new ReasoningProblem('reasoning_limit_exceeded');
                }
                $budget->checkpoint();
                $entry = ['candidate_id' => $context->hypothesis->id, 'rule_key' => $definition->key, 'rule_version' => $definition->version, 'phase' => $definition->phase->name, 'state' => 'disabled', 'reason' => null, 'evidence_ids' => []];
                if ($definition->enabled) {
                    $missing = array_filter($definition->requires, fn ($path) => ! $context->has($path));
                    $dependencyMissing = array_filter($definition->dependsOn, fn ($key) => ($statuses[$key] ?? null) !== 'executed');
                    if ($missing !== [] || $dependencyMissing !== []) {
                        $entry['state'] = 'cannot_evaluate';
                        $entry['reason'] = 'prerequisite_unavailable';
                        $incomplete = true;
                    } else {
                        try {
                            $applicability = $rule->applicability($context);
                            $entry['state'] = $applicability->state;
                            $entry['reason'] = $applicability->reason;
                            if ($applicability->state === 'applicable') {
                                $items = $rule->evaluate($context);
                                if (count($items) > $definition->maxEvidence || count($evidence) + count($items) > 32) {
                                    throw new ReasoningProblem('reasoning_limit_exceeded');
                                }
                                foreach ($items as $item) {
                                    if (! $item instanceof Evidence || $item->ruleKey !== $definition->key || $item->ruleVersion !== $definition->version || $item->hypothesisId !== $context->hypothesis->id || $item->sourceRefs !== $context->hypothesis->target['source_refs']) {
                                        throw new ReasoningProblem('invalid_evidence_provenance');
                                    }
                                    $evidence[] = $item;
                                    $entry['evidence_ids'][] = $item->id;
                                }
                                $entry['state'] = 'executed';
                            } elseif ($applicability->state === 'cannot_evaluate') {
                                $incomplete = true;
                            }
                        } catch (ReasoningProblem $problem) {
                            throw $problem;
                        } catch (\Throwable) {
                            if ($definition->critical) {
                                throw new ReasoningProblem('critical_rule_failed');
                            }
                            $failures[$definition->key] = ($failures[$definition->key] ?? 0) + 1;
                            if ($failures[$definition->key] >= 3) {
                                throw new ReasoningProblem('systematic_rule_failure');
                            }
                            $entry['state'] = 'failed';
                            $entry['reason'] = 'optional_rule_failed';
                            $warnings[$definition->key] = ['code' => 'optional_rule_failed', 'rule_key' => $definition->key];
                            $incomplete = true;
                        }
                    }
                }
                $statuses[$definition->key] = $entry['state'];
                $trace[] = $entry;
            }
            $candidates[$context->hypothesis->id] = ['hypothesis' => $context->hypothesis->toArray(), 'traits' => $context->traits, 'evidence' => array_map(fn ($item) => $item->toArray(), $evidence),
                'scoring' => $this->scorer->score($evidence, $reliability), 'constraints' => [], 'critical_full_validation' => $context->structure['critical_full_validation'],
                'confirmation_flags' => ['incomplete_rule_evaluation' => $incomplete, 'structural_boundary_uncertain' => $context->structure['uncertain'], 'too_few_observations' => $context->column['observed'] < GenericRuleSettings::MINIMUM_OBSERVATIONS]];
        }
        $constrained = $this->constraints->apply($candidates, $this->policy);
        $candidates = $constrained['candidates'];
        $groups = [];
        foreach ($candidates as $id => $candidate) {
            $key = $candidate['hypothesis']['target']['id'].'#'.$candidate['hypothesis']['definition']['competition_group'];
            $groups[$key][] = $id;
        }
        ksort($groups);
        $ambiguities = $targets = [];
        foreach ($groups as $key => $ids) {
            usort($ids, fn ($a, $b) => $candidates[$b]['scoring']['score'] <=> $candidates[$a]['scoring']['score'] ?: strcmp($a, $b));
            $viable = array_values(array_filter($ids, fn ($id) => ! $candidates[$id]['scoring']['veto'] && ! in_array('rejected', array_column($candidates[$id]['constraints'], 'outcome'), true)));
            $leaderId = $viable[0] ?? null;
            $leaderScore = $leaderId === null ? 0 : $candidates[$leaderId]['scoring']['score'];
            $runnerScore = isset($viable[1]) ? $candidates[$viable[1]]['scoring']['score'] : 0;
            $margin = $leaderScore - $runnerScore;
            $required = $leaderId === null ? 0 : $this->policy->thresholds(RiskClass::from($candidates[$leaderId]['hypothesis']['definition']['risk']))['margin'];
            $near = array_values(array_filter($viable, fn ($id) => $candidates[$id]['scoring']['score'] >= ConfidencePolicy::PLAUSIBLE && $leaderScore - $candidates[$id]['scoring']['score'] < $required));
            foreach ($ids as $id) {
                $leading = $id === $leaderId || in_array($id, $near, true);
                $candidates[$id] = [...$candidates[$id], ...$this->confidence->resolve($candidates[$id], $leading ? $margin : 0, $leading, $this->policy)];
            }
            foreach ($ids as $id) {
                $candidates[$id]['competitors'] = array_map(fn ($other) => $this->candidateSummary($candidates[$other]), array_values(array_filter($viable, fn ($other) => $other !== $id && $candidates[$other]['scoring']['score'] >= ConfidencePolicy::PLAUSIBLE)));
                $candidates[$id]['explanation'] = $this->explanations->build($candidates[$id]);
            }
            $targetDecision = $leaderId === null ? (count(array_filter($ids, fn ($id) => $candidates[$id]['scoring']['support'] >= ConfidencePolicy::PLAUSIBLE)) > 0 ? 'contradiction' : 'insufficient_evidence') : $candidates[$leaderId]['decision'];
            $targets[] = ['target_id' => $candidates[$ids[0]]['hypothesis']['target']['id'], 'competition_group' => $candidates[$ids[0]]['hypothesis']['definition']['competition_group'], 'ranked_candidate_ids' => $ids, 'leading_candidate' => count($near) > 1 ? null : $leaderId, 'decision' => $targetDecision];
            if ($leaderId !== null && count($near) > 1) {
                $ambiguities[] = (new Ambiguity($key, array_map(fn ($id) => $this->candidateSummary($candidates[$id]), $near), $margin, 'competing_column_shapes', $candidates[$leaderId]['hypothesis']['definition']['risk']))->toArray();
            } elseif (in_array($targetDecision, ['clarification_required', 'insufficient_evidence', 'contradiction'], true)) {
                $show = $leaderId === null ? array_slice($ids, 0, 2) : [$leaderId];
                $ambiguities[] = (new Ambiguity($key, array_map(fn ($id) => $this->candidateSummary($candidates[$id]), $show), $margin, $targetDecision, $candidates[$show[0]]['hypothesis']['definition']['risk'], $targetDecision === 'insufficient_evidence' ? 'supply_interpretation' : 'confirm_source_context'))->toArray();
            }
        }
        foreach ($constrained['trace'] as $finding) {
            if ($finding['outcome'] === 'clarification' && count($finding['candidate_ids']) > 1) {
                $ids = $finding['candidate_ids'];
                $ambiguities[] = (new Ambiguity($candidates[$ids[0]]['hypothesis']['target']['region_id'], array_map(fn ($id) => $this->candidateSummary($candidates[$id]), $ids), $candidates[$ids[0]]['scoring']['score'] - $candidates[$ids[1]]['scoring']['score'], $finding['reason'], $candidates[$ids[0]]['hypothesis']['definition']['risk']))->toArray();
            }
        }
        ksort($candidates);
        $input = $profile->toArray();
        $manifest = ['engine_version' => self::VERSION, 'structural_engine_version' => $input['engine_version'], 'structural_rules_version' => $input['structural_rules_version'], 'ruleset_version' => self::RULESET_VERSION,
            'source_checksum' => $input['source_checksum'], 'structural_profile_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)), 'reader' => $input['reader'],
            'rules' => $this->registry->snapshot(), 'hypotheses' => $this->catalog->snapshot(), 'constraints' => $this->constraints->snapshot(), 'confidence_policy' => $this->policy->snapshot(), 'generic_rule_settings' => GenericRuleSettings::snapshot(), 'dictionary_versions' => [], 'profile_versions' => []];
        $budget->checkpoint();

        return new ReasoningResult(['schema' => 'wald.reasoning.v1', 'complete' => true, 'ready_for_staging' => false, 'scope' => 'generic_shape_reasoning_only',
            'manifest' => $manifest, 'manifest_hash' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)), 'targets' => $targets, 'hypotheses' => array_values($candidates), 'clarifications' => $ambiguities,
            'diagnostics' => ['phases' => array_map(fn ($phase) => $phase->name, RulePhase::cases()), 'rule_evaluations' => $trace, 'constraint_findings' => $constrained['trace'], 'warnings' => array_values($warnings), 'evaluations' => $executions]]);
    }

    private function candidateSummary(array $candidate): array
    {
        return ['id' => $candidate['hypothesis']['id'], 'label' => $candidate['hypothesis']['definition']['label'], 'source_label' => $candidate['hypothesis']['target']['label'], 'strength' => $candidate['scoring']['score'], 'veto' => $candidate['scoring']['veto'],
            'confidence' => $candidate['confidence'], 'decision' => $candidate['decision'],
            'evidence_summary' => ['support' => $candidate['scoring']['support'], 'penalty' => $candidate['scoring']['penalty'], 'families' => $candidate['scoring']['supporting_families']],
            'evidence_ids' => array_column($candidate['evidence'], 'evidence_id'), 'source_refs' => $candidate['hypothesis']['target']['source_refs'], 'samples' => $candidate['hypothesis']['target']['samples']];
    }
}
