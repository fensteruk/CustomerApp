<?php

namespace App\SourceImport\Integration;

/**
 * Resolves the source-site key from controlled dictionary roles.
 *
 * New master exports must use CustomerCode. Exact historical workbook hashes
 * retain their previously accepted identity contract; no code is invented.
 */
final class MasterExportSiteIdentity
{
    public const KIND = 'CUSTOMER_CODE';

    private const LEGACY_WORKBOOK_HASHES = [
        ReviewedWorkbookSelection::CHECKSUM,
        'a9a5f2214d3b687af9043bb0f5232d5d105bb0e9c677c1e82e0b1e0e82b285df',
    ];

    /** @param array<string, array<int, bool>> $roles */
    public function columns(array $roles, string $workbookHash, bool $requiresCustomerCode = true): array
    {
        $customerCode = array_keys($roles['source_customer_code'] ?? []);
        if (count($customerCode) > 1) {
            throw new ImportConflict('customer_code_column_ambiguous');
        }

        $siteNames = array_keys($roles['transitional_site_clue'] ?? []);
        if (count($siteNames) > 1) {
            throw new ImportConflict('source_site_name_column_ambiguous');
        }

        if (count($customerCode) === 1) {
            return [
                'kind' => self::KIND,
                'identity_column' => (int) $customerCode[0],
                'site_name_column' => isset($siteNames[0]) ? (int) $siteNames[0] : null,
                'legacy' => false,
            ];
        }

        if ($requiresCustomerCode && ! in_array($workbookHash, self::LEGACY_WORKBOOK_HASHES, true)) {
            throw new ImportConflict('customer_code_missing');
        }

        foreach (['source_site_identity' => 'SOURCE_SITE_ID', 'transitional_site_clue' => 'EXACT_SITE_NAME'] as $role => $kind) {
            $columns = array_keys($roles[$role] ?? []);
            if (count($columns) > 1) {
                throw new ImportConflict('required_site_column_ambiguous');
            }
            if (count($columns) === 1) {
                return [
                    'kind' => $kind,
                    'identity_column' => (int) $columns[0],
                    'site_name_column' => $kind === 'EXACT_SITE_NAME' ? (int) $columns[0] : ($siteNames[0] ?? null),
                    'legacy' => true,
                ];
            }
        }

        throw new ImportConflict('required_site_column_unresolved');
    }

    public function customerCode(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new ImportConflict('customer_code_missing');
        }

        $code = trim((string) $value);
        if ($code === '') {
            throw new ImportConflict('customer_code_missing');
        }
        if (mb_strlen($code) > 100 || preg_match('/[\x00-\x1f\x7f<>]/', $code)) {
            throw new ImportConflict('customer_code_invalid');
        }

        return $code;
    }

    public function siteName(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new ImportConflict('invalid_source_site_name');
        }

        $name = trim((string) $value);
        if (mb_strlen($name) > 512 || preg_match('/[\x00-\x1f\x7f<>]/', $name)) {
            throw new ImportConflict('invalid_source_site_name');
        }

        return $name;
    }
}
