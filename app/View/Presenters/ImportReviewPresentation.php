<?php

namespace App\View\Presenters;

use Illuminate\Support\Carbon;

/** Display decisions only. All transitions still use the existing authorised Wald actions. */
final class ImportReviewPresentation
{
    public static function stage(array $rows): array
    {
        $included = array_filter($rows, fn ($row) => ! $row['excluded']);

        return ['rows' => count($rows), 'included' => count($included), 'excluded' => count($rows) - count($included),
            'plots' => count(array_unique(array_column(array_column($included, 'facts'), 'plot'))),
            'blocked' => count(array_filter($rows, fn ($row) => $row['issues'] !== [])),
            'customer_care' => count(array_filter($rows, fn ($row) => $row['excluded'] && strtoupper(trim($row['provenance']['raw_call_type'] ?? '')) === 'CU4'))];
    }

    public static function forSelection(array $import, array $item): array
    {
        $state = $item['run']['state'];
        $closed = in_array($import['state'], ['FAILED', 'SUPERSEDED']) || in_array($state, ['COMMITTED', 'SUPERSEDED']);
        $questions = collect($item['questions'])->where('state', '!=', 'ANSWERED')->count();
        $blockers = $item['preview']['payload']['blockers'] ?? [];
        $expired = isset($item['preview']['expires_at']) && now('UTC')->greaterThanOrEqualTo(Carbon::parse($item['preview']['expires_at']));
        $blocked = ($item['stage_summary']['blocked'] ?? 0) > 0 || $blockers !== [];
        $refresh = $item['analysis_needs_refresh'];
        $action = match (true) {
            $closed => null,
            $refresh => 'reanalyse',
            $state === 'NEEDS_CLARIFICATION' && $questions > 0 => 'questions',
            in_array($state, ['UPLOADED', 'NEEDS_CLARIFICATION']) => 'analyse',
            $state === 'REQUIRES_REVIEW' => 'preview',
            $expired && in_array($state, ['REVIEWED', 'READY_TO_COMMIT']) => 'preview',
            $blocked => null,
            $state === 'REVIEWED' && $item['preview'] => 'approve',
            $state === 'READY_TO_COMMIT' && $item['preview'] => 'commit',
            default => null,
        };
        $label = match (true) {
            $state === 'COMMITTED' => 'Applied to CustomerApp',
            $closed => 'Historical review',
            $refresh => 'Re-analysis needed',
            $expired => 'Preview expired',
            $state === 'FAILED' => 'Analysis stopped',
            $questions > 0 && $state === 'NEEDS_CLARIFICATION' => 'Needs your input',
            $blocked => 'Needs attention',
            default => ['UPLOADED' => 'Ready to analyse', 'ANALYSING' => 'Analysing', 'NEEDS_CLARIFICATION' => 'Ready to continue',
                'REQUIRES_REVIEW' => 'Ready to review', 'REVIEWED' => 'Ready to approve', 'READY_TO_COMMIT' => 'Ready to apply', 'COMMITTING' => 'Applying'][$state] ?? 'Status unavailable',
        };
        $step = match (true) {
            $refresh || $state === 'FAILED' => 2,
            $expired => 3,
            default => ['UPLOADED' => 2, 'ANALYSING' => 2, 'NEEDS_CLARIFICATION' => 2, 'REQUIRES_REVIEW' => 3, 'REVIEWED' => 4,
                'READY_TO_COMMIT' => 5, 'COMMITTING' => 5, 'COMMITTED' => 6][$state] ?? null,
        };

        return compact('action', 'label', 'step', 'closed', 'questions', 'blockers', 'expired', 'blocked', 'refresh');
    }

    public static function issue(string $code): string
    {
        return match (strtoupper($code)) {
            'BLOCKED_STAGED_RECORDS' => 'Some source rows need correction before this site can be applied.',
            'PRODUCT_QUANTITY_CONFLICT' => 'This plot has different quantities for the same product. Check the source rows.',
            'UNKNOWN_CALL_TYPE' => 'A Call Type has no approved CustomerApp meaning.',
            'SOURCE_SITE_BINDING_REQUIRED', 'STALE_SOURCE_BINDING' => 'The exact site link needs reviewing.',
            'OLDER_EXPORT_REFUSED', 'STALE_SOURCE_STREAM' => 'A newer export is available. Review the current import.',
            'STALE_PROJECTION' => 'The site has changed since this preview. Create a fresh preview.',
            'INVALID_PRODUCT_QUANTITY', 'INVALID_PRODUCT_CELL_TYPE' => 'A product quantity could not be read safely.',
            default => 'Wald could not safely confirm this part of the import. Review the source evidence before continuing.',
        };
    }

    public static function question(string $key): string
    {
        return match ($key) {
            'structure:plot_reference' => 'Which column identifies the plot?',
            'structure:call_number' => 'Which column contains the Call No.?',
            'structure:customer_code' => 'Which column contains the CustomerCode?',
            'structure:call_type' => 'Which column contains the Call Type?',
            'structure:completion' => 'Which column records source completion?',
            default => str_starts_with($key, 'structure:') ? 'Which source column should Wald use?' : 'Which approved meaning applies to this source value?',
        };
    }
}
