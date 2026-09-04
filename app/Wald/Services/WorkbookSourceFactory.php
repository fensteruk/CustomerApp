<?php

namespace App\Wald\Services;

use App\Wald\Contracts\WorkbookSource;

final class WorkbookSourceFactory
{
    /** Internal, trusted local paths only. Future HTTP callers must authorise through the import session. */
    public function open(string $path, string $format, AnalysisBudget $budget): WorkbookSource
    {
        if (str_contains($path, '://') || str_starts_with($path, '\\\\') || str_starts_with($path, '//') || ! is_file($path) || ! is_readable($path)) {
            throw new AnalysisProblem('unreadable_workbook');
        }
        $budget->guard('file_bytes', filesize($path));
        $budget->reserve(filesize($path) * 2);

        return match (strtolower($format)) {
            'xlsx' => new XlsxWorkbookSource($path, $budget),
            'csv' => new CsvWorkbookSource($path, $budget),
            default => throw new AnalysisProblem('unsupported_format'),
        };
    }
}
