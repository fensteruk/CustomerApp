<?php

namespace App\Services;

use App\Data\WorkbookInterpretation;
use App\Data\WorkbookSheetInterpretation;
use App\Models\User;
use App\Models\WorkbookInterpretationProfile;

class WorkbookInterpretationProfileService
{
    /** @return array{kind: 'exact'|'likely'|'none', score: int, profile: WorkbookInterpretationProfile|null, matched_sheet: string|null} */
    public function match(string $sourceNamespace, WorkbookInterpretation $interpretation): array
    {
        if ($interpretation->sheets === []) {
            return ['kind' => 'none', 'score' => 0, 'profile' => null, 'matched_sheet' => null];
        }

        foreach ($interpretation->sheets as $sheet) {
            if (! $sheet->visible) {
                continue;
            }
            $exact = WorkbookInterpretationProfile::query()
                ->where('source_namespace', $sourceNamespace)
                ->where('structural_fingerprint', $this->fingerprintForSheet($sheet))
                ->latest('version')
                ->first();
            if ($exact !== null) {
                return ['kind' => 'exact', 'score' => 100, 'profile' => $exact, 'matched_sheet' => $sheet->sheet];
            }
        }

        $best = null;
        $bestSheet = null;
        $bestScore = 0;
        $candidates = WorkbookInterpretationProfile::query()->where('source_namespace', $sourceNamespace)->latest()->limit(50)->get();
        foreach ($interpretation->sheets as $sheet) {
            if (! $sheet->visible) {
                continue;
            }
            $current = $this->profileDataForSheet($sheet);
            foreach ($candidates as $candidate) {
                $score = $this->similarity(
                    $current['headers'],
                    $current['types'],
                    $sheet->sheet,
                    $candidate->normalised_headers,
                    $candidate->type_profile,
                    $candidate->sheet_identifier,
                );
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                    $bestSheet = $sheet->sheet;
                }
            }
        }

        return $best !== null && $bestScore >= 65
            ? ['kind' => 'likely', 'score' => $bestScore, 'profile' => $best, 'matched_sheet' => $bestSheet]
            : ['kind' => 'none', 'score' => $bestScore, 'profile' => null, 'matched_sheet' => null];
    }

    /**
     * @param  array{sheet: string, header_row: int, columns: list<array{source_index: int, semantic_role: string, subtype?: string|null}>}  $mapping
     */
    public function save(User $actor, string $sourceNamespace, WorkbookInterpretation $interpretation, array $mapping): WorkbookInterpretationProfile
    {
        $sheet = collect($interpretation->sheets)->firstWhere('sheet', $mapping['sheet']);
        if ($sheet === null) {
            throw new \DomainException('The confirmed worksheet cannot be profiled.');
        }
        $data = $this->profileDataForSheet($sheet);
        $structuralFingerprint = $this->fingerprintForSheet($sheet);
        $existing = WorkbookInterpretationProfile::query()
            ->where('source_namespace', $sourceNamespace)
            ->where('structural_fingerprint', $structuralFingerprint)
            ->latest('version')
            ->first();

        if ($existing !== null && $existing->confirmed_mappings === $mapping) {
            return $existing;
        }

        return WorkbookInterpretationProfile::query()->create([
            'source_namespace' => $sourceNamespace,
            'sheet_identifier' => $mapping['sheet'],
            'structural_fingerprint' => $structuralFingerprint,
            'normalised_headers' => $data['headers'],
            'type_profile' => $data['types'],
            'confirmed_mappings' => $mapping,
            'snapshot_scope' => 'represented_sites',
            'version' => ($existing?->version ?? 0) + 1,
            'confirmed_by_user_id' => $actor->id,
        ]);
    }

    /** @return array{headers: list<string>, types: list<array<string, float|int>>} */
    private function profileDataForSheet(WorkbookSheetInterpretation $sheet): array
    {
        return [
            'headers' => array_map(fn ($column): string => $column->normalisedHeader, $sheet->columns),
            'types' => array_map(fn ($column): array => [
                'text' => $column->profile['text_percent'],
                'integer' => $column->profile['integer_percent'],
                'decimal' => $column->profile['decimal_percent'],
                'date' => $column->profile['date_percent'],
                'boolean' => $column->profile['boolean_like_percent'],
            ], $sheet->columns),
        ];
    }

    public function fingerprintForSheet(WorkbookSheetInterpretation $sheet): string
    {
        return hash('sha256', json_encode([
            'sheet' => trim((string) preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($sheet->sheet)))),
            'headers' => array_map(fn ($column): string => $column->normalisedHeader, $sheet->columns),
            'types' => array_map(fn ($column): array => [
                'text' => $column->profile['text_percent'],
                'integer' => $column->profile['integer_percent'],
                'decimal' => $column->profile['decimal_percent'],
                'date' => $column->profile['date_percent'],
                'boolean' => $column->profile['boolean_like_percent'],
            ], $sheet->columns),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<string>  $leftHeaders
     * @param  list<array<string, float|int>>  $leftTypes
     * @param  list<string>  $rightHeaders
     * @param  list<array<string, float|int>>  $rightTypes
     */
    private function similarity(array $leftHeaders, array $leftTypes, string $leftSheet, array $rightHeaders, array $rightTypes, string $rightSheet): int
    {
        $leftSet = collect($leftHeaders)->filter()->unique();
        $rightSet = collect($rightHeaders)->filter()->unique();
        $union = $leftSet->merge($rightSet)->unique()->count();
        $jaccard = $union === 0 ? 0 : $leftSet->intersect($rightSet)->count() / $union;
        $ordered = 0;
        $maximum = max(count($leftHeaders), count($rightHeaders), 1);
        for ($index = 0; $index < min(count($leftHeaders), count($rightHeaders)); $index++) {
            if ($leftHeaders[$index] === $rightHeaders[$index]) {
                $ordered++;
            }
        }
        $orderedRatio = $ordered / $maximum;
        $typeMatches = 0;
        $typeCompared = 0;
        foreach ($leftHeaders as $leftIndex => $header) {
            $rightIndex = array_search($header, $rightHeaders, true);
            if ($header === '' || $rightIndex === false || ! isset($leftTypes[$leftIndex], $rightTypes[$rightIndex])) {
                continue;
            }
            $typeCompared++;
            $difference = collect(['text', 'integer', 'decimal', 'date', 'boolean'])
                ->sum(fn (string $key): float => abs((float) ($leftTypes[$leftIndex][$key] ?? 0) - (float) ($rightTypes[$rightIndex][$key] ?? 0)));
            if ($difference <= 80) {
                $typeMatches++;
            }
        }
        $typeRatio = $typeCompared === 0 ? 0 : $typeMatches / $typeCompared;
        $sheetScore = mb_strtolower(trim($leftSheet)) === mb_strtolower(trim($rightSheet)) ? 1 : 0;

        return (int) round(($jaccard * 45) + ($orderedRatio * 25) + ($typeRatio * 20) + ($sheetScore * 10));
    }
}
