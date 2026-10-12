<?php

namespace App\SourceImport\Integration;

use App\Wald\Services\AnalysisProblem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Opt-in, private operational diagnostics. Never log source or exception payloads. */
final class WaldDryRunDiagnostics
{
    public function stopped(AnalysisProblem|InvalidArgumentException $exception, int $started): void
    {
        if (config('wald_import.dry_run_diagnostics', false) !== true) {
            return;
        }

        $code = $exception instanceof AnalysisProblem ? $exception->problemCode : match ($exception->getMessage()) {
            'knowledge_payload_limit', 'transient_evidence_hash_limit', 'knowledge_requires_plain_data' => $exception->getMessage(),
            default => 'invalid_argument',
        };
        $allowedCodes = ['corrupt_archive', 'duplicate_cell_reference', 'invalid_cell_reference', 'invalid_csv',
            'invalid_shared_string', 'invalid_workbook', 'invalid_xml', 'missing_workbook_part',
            'resource_limit_exceeded', 'unreadable_workbook', 'unsafe_archive', 'unsafe_xml',
            'unsupported_encoding', 'unsupported_format', 'unsupported_sheet_type',
            'knowledge_payload_limit', 'transient_evidence_hash_limit', 'knowledge_requires_plain_data', 'invalid_argument'];
        $context = $exception instanceof AnalysisProblem ? $exception->context : [];
        $limit = $context['limit'] ?? null;
        $allowedLimits = ['file_bytes', 'archive_bytes', 'entry_bytes', 'entries', 'sheets', 'cells',
            'rows', 'columns', 'merges', 'styles', 'strings', 'string_bytes', 'cell_bytes', 'node_bytes',
            'regions', 'seconds', 'memory_bytes', 'time_or_memory', 'analysis_seconds'];

        Log::warning('Wald Analyse-only stopped safely', [
            'diagnostic_id' => (string) Str::uuid(),
            'exception_type' => $exception instanceof AnalysisProblem ? 'analysis_problem' : 'invalid_argument',
            'problem_code' => in_array($code, $allowedCodes, true) ? $code : 'unclassified_analysis_problem',
            'limit' => in_array($limit, $allowedLimits, true) ? $limit : null,
            'maximum' => is_int($context['maximum'] ?? null) ? $context['maximum'] : null,
            'elapsed_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_memory_limit' => ini_get('memory_limit'),
            'php_max_execution_time' => (int) ini_get('max_execution_time'),
        ]);
    }
}
