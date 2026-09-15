<?php

namespace App\SourceImport\Integration;

use App\SourceImport\Knowledge\Canonical;

/** Structural discovery only; the controlled dictionary remains the sole meaning authority. */
final class PilotWorkbookDiscovery
{
    public const MAX_PARENT_ROWS = 5000;

    public function inspect(object $upload): array
    {
        [, $sheets, $snapshot] = (new WorkbookStager)->inspect($upload);
        $data = $snapshot->data;
        if (($data['composite_candidate_count'] ?? 0) > 1) {
            throw new ImportConflict('ambiguous_composite_table');
        }
        if (count($data['tables']) !== 1 || count($sheets) !== 1) {
            throw new ImportConflict('bounded_single_table_required');
        }
        $tableId = array_key_first($data['tables']);
        $table = $data['tables'][$tableId];
        $sheet = array_values($sheets)[0];
        if (array_diff($table['warnings'], ['no_clear_header']) !== [] || $sheet->visibility !== 'visible'
            || $sheet->hiddenRows !== [] || $sheet->hiddenColumns !== [] || $sheet->merges !== []) {
            throw new ImportConflict('unsafe_or_unsupported_structure');
        }

        $roles = [];
        foreach ($data['questions'] as $question) {
            if (($question['type'] ?? null) !== 'STRUCTURAL') {
                continue;
            }
            foreach ($question['candidates'] ?? [] as $candidate) {
                if (($candidate['table'] ?? null) === $tableId) {
                    $roles[$candidate['role']][$candidate['column']] = true;
                }
            }
        }
        $callColumn = $this->single($roles, 'call_reference', 'required_call_column_unresolved');
        $callTypeColumn = $this->single($roles, 'call_type', 'required_call_type_column_unresolved');
        [$siteKind, $siteColumn] = $this->site($roles);

        $seen = [];
        $sources = [];
        $included = 0;
        $excluded = 0;
        $dataStart = (int) ($table['data_start_row'] ?? ($table['header_range']['end_row'] + 1));
        $dataEnd = (int) ($table['data_end_row'] ?? $table['range']['end_row']);
        foreach ($sheet->cells as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataEnd
                || ! array_filter($cells, fn ($cell): bool => $cell->rawValue !== null && $cell->rawValue !== '')) {
                continue;
            }
            if (count($seen) >= self::MAX_PARENT_ROWS) {
                throw new ImportConflict('pilot_workbook_row_limit_exceeded');
            }
            foreach ($cells as $cell) {
                if ($cell->formula !== null || $cell->type === 'error') {
                    throw new ImportConflict('unsafe_source_cell');
                }
            }
            $call = (string) ($cells[$callColumn]->rawValue ?? '');
            if (! preg_match('/^[0-9]{1,100}$/D', $call) || ! preg_match('/[1-9]/', $call)) {
                throw new ImportConflict('invalid_call_number');
            }
            if (isset($seen[$call])) {
                throw new ImportConflict('duplicate_call_number');
            }
            $seen[$call] = true;
            $site = trim((string) ($cells[$siteColumn]->rawValue ?? ''));
            if ($site === '' || mb_strlen($site) > 512 || preg_match('/[\x00-\x1f\x7f<>]/', $site)) {
                throw new ImportConflict('invalid_source_site');
            }
            $rawCallType = $cells[$callTypeColumn]->rawValue ?? null;
            $selection = (new ReviewedWorkbookSelection)->treatment(
                $upload->workbook_hash,
                $sheet->id,
                (int) $rowNumber,
                $call,
                $site,
                is_string($rawCallType) ? $rawCallType : '',
            );
            if ($selection['excluded']) {
                $excluded++;

                continue;
            }
            $hash = Canonical::hash([$siteKind, $site]);
            $sources[$hash] ??= ['kind' => $siteKind, 'identity' => $site, 'hash' => $hash, 'rows' => 0];
            $sources[$hash]['rows']++;
            $included++;
        }
        if ($seen === [] || $sources === []) {
            throw new ImportConflict('no_source_records');
        }
        ksort($sources, SORT_STRING);

        return [
            'schema' => 'customerapp.wald-pilot-discovery.v2',
            'analysis_hash' => $data['analysis_hash'],
            'requires_confirmation' => in_array('no_clear_header', $table['warnings'], true),
            'sheet' => $sheet->id,
            'header' => [$table['header_range']['start_row'], $table['header_range']['end_row']],
            'logical_table' => $table['canonical_reference'] ?? $table['range']['address'],
            'sources' => array_values($sources),
            'source_count' => count($sources),
            'record_count' => count($seen),
            'included_count' => $included,
            'excluded_count' => $excluded,
        ];
    }

    private function single(array $roles, string $role, string $error): int
    {
        $columns = array_keys($roles[$role] ?? []);
        if (count($columns) !== 1) {
            throw new ImportConflict($error);
        }

        return (int) $columns[0];
    }

    private function site(array $roles): array
    {
        foreach (['source_site_identity' => 'SOURCE_SITE_ID', 'transitional_site_clue' => 'EXACT_SITE_NAME'] as $role => $kind) {
            $columns = array_keys($roles[$role] ?? []);
            if (count($columns) === 1) {
                return [$kind, (int) $columns[0]];
            }
            if (count($columns) > 1) {
                throw new ImportConflict('required_site_column_ambiguous');
            }
        }

        throw new ImportConflict('required_site_column_unresolved');
    }
}
