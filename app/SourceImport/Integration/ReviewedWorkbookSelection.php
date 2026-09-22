<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;

/** Keep globally approved exclusions separate from checksum-scoped artifact exceptions. */
final class ReviewedWorkbookSelection
{
    public const CHECKSUM = 'ee07e1f7296cf88cf548748e624ada576e1cf20120ba2c0be0617f446fb9f893';

    public const VERSION = 'customerapp.reviewed-workbook.dec050-dec051-dec069.v2';

    /** Caller supplies server-computed checksum and original reader observations, never browser authority. */
    public function treatment(string $checksum, string $sheetId, int $row, string $callNo, string $site, string $rawCallType): array
    {
        $result = ['raw_call_type' => $rawCallType, 'canonical_override' => null, 'excluded' => false, 'approvals' => [], 'selection_version' => self::VERSION];
        if (CustomerAppDictionary::excludesCallType($rawCallType)) {
            $result['excluded'] = true;
            $result['approvals'][] = 'DEC-069';
        }
        if (! hash_equals(self::CHECKSUM, $checksum)) {
            return $result;
        }
        if ($rawCallType === 'CC!') {
            $result['canonical_override'] = 'CC1';
            $result['approvals'][] = 'DEC-050';
        }
        if ($rawCallType === 'CM2') {
            $result['excluded'] = true;
            $result['approvals'][] = 'DEC-050';
        }
        if ($sheetId === 'sheet-1' && $row === 32 && $callNo === '5181' && $site === 'Nick TEST') {
            $result['excluded'] = true;
            $result['approvals'][] = 'DEC-051';
        }

        return $result;
    }
}
