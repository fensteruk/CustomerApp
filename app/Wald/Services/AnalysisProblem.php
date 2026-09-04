<?php

namespace App\Wald\Services;

use RuntimeException;

final class AnalysisProblem extends RuntimeException
{
    public function __construct(public readonly string $problemCode, public readonly array $context = [])
    {
        parent::__construct(match ($problemCode) {
            'unsupported_format' => 'Wald supports XLSX and CSV. Export this workbook to a supported format.',
            'resource_limit_exceeded' => 'This workbook exceeds the safe analysis limit. Use a smaller source workbook.',
            'unsafe_archive', 'unsafe_xml' => 'The workbook contains unsupported or unsafe content.',
            'unsupported_encoding' => 'The CSV encoding is unsupported. Export it as UTF-8.',
            default => 'Wald could not read the workbook safely. Check the file and try again.',
        });
    }

    public function toArray(): array
    {
        return ['code' => $this->problemCode, 'message' => $this->getMessage(), 'context' => $this->context, 'complete' => false];
    }
}
