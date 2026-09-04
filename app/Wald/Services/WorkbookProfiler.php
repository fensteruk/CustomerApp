<?php

namespace App\Wald\Services;

use App\Wald\Contracts\WorkbookProfile;
use App\Wald\Contracts\WorkbookSource;

final class WorkbookProfiler
{
    public const ENGINE_VERSION = 'wald-0.2.0';

    public const STRUCTURE_VERSION = 'wald.structure.v1.1';

    public function __construct(private readonly WorkbookSourceFactory $sources, private readonly SheetProfiler $sheets) {}

    public function profile(string $path, string $format, ?AnalysisBudget $budget = null): WorkbookProfile
    {
        $budget ??= new AnalysisBudget;
        $source = $this->sources->open($path, $format, $budget);
        try {
            return $this->analyse($source, hash_file('sha256', $path), $budget);
        } finally {
            $source->close();
        }
    }

    /** Pure boundary for future approved readers and test sources; never stages or commits records. */
    public function analyse(WorkbookSource $source, string $checksum, ?AnalysisBudget $budget = null): WorkbookProfile
    {
        $budget ??= new AnalysisBudget;
        $sheets = $signature = [];
        $total = 0;
        foreach ($source->sheets() as $sheet) {
            $budget->guard('sheets', count($sheets) + 1);
            $profile = $this->sheets->profile($sheet, $budget);
            $profile['source_checksum'] = $checksum;
            $total += $profile['non_empty_cell_count'];
            $budget->guard('cells', $total);
            $sheets[] = $profile;
            $signature[] = ['name' => $profile['name'], 'visibility' => $profile['visibility'], 'dimensions' => [$profile['max_used_row'], $profile['max_used_column']], 'density' => $profile['density'], 'value_types' => $profile['value_types'], 'merges' => $profile['merge_ranges'], 'region_signatures' => array_values(array_filter(array_column($profile['regions'], 'structure_signature'))), 'repeated_block_counts' => array_values(array_map('count', $profile['repeated_blocks']))];
            unset($sheet);
        }
        $metadata = $source->metadata();
        $warnings = $metadata['warnings'] ?? [];
        foreach ($sheets as $sheet) {
            $warnings = [...$warnings, ...$sheet['warnings']];
        }
        $fingerprint = ['schema' => 'wald.structure-fingerprint.v1', 'sheets' => $signature];

        return new WorkbookProfile(['schema' => 'wald.workbook-profile.v1', 'source_checksum' => $checksum,
            'engine_version' => self::ENGINE_VERSION, 'structural_rules_version' => self::STRUCTURE_VERSION,
            'reader' => $metadata, 'complete' => true, 'scope' => 'structural_analysis_only', 'ready_for_staging' => false,
            'sheet_count' => count($sheets), 'hidden_sheet_count' => count(array_filter($sheets, fn ($sheet) => $sheet['visibility'] !== 'visible')),
            'non_empty_cell_count' => $total, 'sheets' => $sheets, 'warnings' => array_values(array_unique($warnings)),
            'structural_fingerprint' => ['algorithm' => 'sha256', 'value' => hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR)), 'components' => $fingerprint],
        ]);
    }
}
