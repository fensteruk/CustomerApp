<?php

namespace App\SourceImport\Integration;

/** Presentation evidence only: this never selects a column or approves a source profile. */
final class DryRunHeaderConflict extends ImportConflict
{
    public function __construct(string $reason, public readonly array $guidance)
    {
        parent::__construct($reason);
    }

    public static function fromInspection(ImportConflict $conflict, array $inspection): self
    {
        [, $sheets, $snapshot] = $inspection;
        $roles = [];
        foreach ($snapshot->data['questions'] as $question) {
            if (($question['type'] ?? null) !== 'STRUCTURAL') {
                continue;
            }
            foreach ($question['candidates'] ?? [] as $candidate) {
                $roles[$candidate['role']][$candidate['column']] = true;
            }
        }
        $labels = [];
        foreach ($snapshot->data['tables'] as $table) {
            foreach ($sheets as $sheet) {
                if ($sheet->id !== $table['sheet_id']) {
                    continue;
                }
                foreach ($sheet->cells[$table['header_range']['start_row']] ?? [] as $cell) {
                    if (is_string($cell->rawValue) && trim($cell->rawValue) !== '') {
                        $labels[] = mb_substr(trim($cell->rawValue), 0, 100);
                    }
                }
            }
        }
        $guidance = ['No data was applied. No column mapping was approved.'];
        if ($labels !== []) {
            $guidance[] = 'Observed header labels: '.implode(', ', array_slice(array_unique($labels), 0, 30)).'.';
        }
        foreach ([
            'call_reference' => ['Call Number', 'Call No. or Call Number (approved case, spacing and punctuation variants)'],
            'source_customer_code' => ['CustomerCode for a new master export', 'CustomerCode, CustomerNo or Customer Number'],
            'plot_reference' => ['Plot hierarchy', 'Plot Ref'],
            'call_type' => ['Call Type', 'Call Type'],
        ] as $role => [$name, $expected]) {
            $count = count($roles[$role] ?? []);
            if ($count !== 1) {
                $guidance[] = $name.': '.($count === 0 ? 'no recognised column' : 'multiple competing columns').'. Recognised labels: '.$expected.'.';
            }
        }
        $guidance[] = 'Outside an approved source profile, labels such as Call, Customer and Site Name do not approve a different source meaning. Ask Fenster to confirm the export contract; do not rename columns or discard repeated rows merely to bypass this check.';

        return new self($conflict->getMessage(), $guidance);
    }
}
