<?php

use App\Wald\Contracts\WorkbookProfile;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures;

function customerWaldProfile(array $rows): WorkbookProfile
{
    $values = new ValueProfiler;
    $profiler = new WorkbookProfiler(
        new WorkbookSourceFactory,
        new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values),
    );

    return $profiler->profile(WaldFixtures::xlsx([['rows' => $rows]]), 'xlsx');
}

function customerWaldCandidate(array $result, int $column, string $shape): array
{
    return array_values(array_filter(
        $result['hypotheses'],
        fn (array $candidate): bool => $candidate['hypothesis']['target']['column'] === $column
            && $candidate['hypothesis']['definition']['key'] === $shape,
    ))[0];
}

afterEach(fn () => WaldFixtures::cleanup());

it('keeps House No and Sales Plot equally plausible and asks for clarification', function () {
    $rows = [1 => [1 => 'House No.', 2 => 'Sales Plot']];

    for ($row = 1; $row <= 10; $row++) {
        $rows[$row + 1] = [1 => sprintf('H%03d', $row), 2 => sprintf('P%03d', $row)];
    }

    $profile = customerWaldProfile($rows);
    $first = (new ReasoningEngine)->reason($profile)->toArray();
    $second = (new ReasoningEngine)->reason($profile)->toArray();

    foreach ([1, 2] as $column) {
        $candidate = customerWaldCandidate($first, $column, 'identifier_like');

        expect($candidate['decision'])->toBe('clarification_required')
            ->and($candidate['evidence'])->not->toBeEmpty()
            ->and($candidate['confidence']['is_probability'])->toBeFalse()
            ->and($candidate['explanation']['why_wald_thinks_this'])->not->toBeEmpty();
    }

    $ambiguity = array_values(array_filter(
        $first['clarifications'],
        fn (array $item): bool => $item['reason'] === 'exclusive_role_near_tie',
    ))[0];

    expect($ambiguity['leading_candidate'])->toBeNull()
        ->and($ambiguity['competing_candidates'])->toHaveCount(2)
        ->and($ambiguity['question_type_hint'])->toBe('choose_candidate')
        ->and($second)->toBe($first);
});

it('preserves unknown business text without defining or correcting its meaning', function () {
    $rows = [
        1 => [1 => 'Call Type', 2 => 'Operational Date', 3 => 'Quantity'],
        2 => [1 => 'CC!', 2 => '2026-09-10', 3 => 2],
        3 => [1 => 'ZZ9', 2 => '2026-09-11', 3 => 4],
    ];

    $profile = customerWaldProfile($rows)->toArray();
    $reasoning = (new ReasoningEngine)->reason(customerWaldProfile($rows))->toArray();
    $encoded = json_encode([$profile, $reasoning], JSON_THROW_ON_ERROR);

    expect($encoded)->toContain('CC!', 'ZZ9', 'date_like', 'quantity_like')
        ->and($encoded)->not->toContain('CC1', 'requested_date', 'date_agreed', 'proposal_date')
        ->and($reasoning['manifest']['dictionary_versions'])->toBe([])
        ->and($reasoning['ready_for_staging'])->toBeFalse();
});

it('has no application persistence authorization network or business-domain coupling', function () {
    $root = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Wald';
    $forbidden = [
        'App\\Models',
        'App\\Enums',
        'Illuminate\\Database',
        'Illuminate\\Auth',
        'Illuminate\\Support\\Facades\\DB',
        'Illuminate\\Support\\Facades\\Gate',
        'Illuminate\\Support\\Facades\\Storage',
        'ConstructionImport',
        'WorkflowStage',
        'SiteApp',
        'OpenAI',
        'Anthropic',
        'Gemini',
    ];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $files = 0;

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $files++;
        $contents = file_get_contents($file->getPathname());

        foreach ($forbidden as $term) {
            expect($contents)->not->toContain($term);
        }
    }

    expect($files)->toBe(52);
});
