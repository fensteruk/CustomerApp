<?php

namespace App\Demo;

final class SyntheticImportStudioScenario
{
    public const VERSION = 'synthetic-import-demo-v1';

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return [
            'version' => self::VERSION,
            'title' => 'Synthetic Meadow morning export',
            'safe_file_label' => 'synthetic-redzebra-example.xlsx',
            'export_date' => '8 September 2026',
            'export_slot' => 'MORNING',
            'uploader' => 'Current Fenster Office Staff user',
            'latest_confirmation' => 'Confirmed as the latest synthetic morning export.',
            'analysis' => [
                'mode' => 'Precomputed demonstration — the Wald engine has not run',
                'confidence' => 'High structural confidence',
                'columns' => ['Site Name', 'Plot', 'Call No.', 'Call Type', 'VS', 'PSU', 'BF', 'Completed'],
                'evidence' => 'One visible table with stable headings and 7 selected fictional records.',
            ],
            'site' => [
                'customer' => 'Northstar Homes (fictional)',
                'name' => 'Synthetic Meadow',
                'source_identity' => 'SYNTHETIC-MEADOW-01',
                'binding' => 'Proposed source-site link for review only',
            ],
            'records' => [
                ['plot' => 'Plot 001', 'service' => 'Windows', 'call' => 'PC1', 'products' => '6 windows', 'result' => 'New outstanding service'],
                ['plot' => 'Plot 002', 'service' => 'Windows', 'call' => 'PC1', 'products' => '5 windows', 'result' => 'Quantity update preview'],
                ['plot' => 'Plot 003', 'service' => 'Cavity Closers', 'call' => 'CC!', 'products' => '4 closers', 'result' => 'Clarification required'],
                ['plot' => 'Plot 004', 'service' => 'CML', 'call' => 'CM1', 'products' => '2 doors', 'result' => 'New outstanding service'],
                ['plot' => 'Plot 005', 'service' => 'Windows', 'call' => 'PC1', 'products' => '7 windows', 'result' => 'Completion observed'],
                ['plot' => 'Plot 006', 'service' => 'Windows', 'call' => 'PC1', 'products' => '1 bifold', 'result' => 'New outstanding service'],
                ['plot' => 'Plot 007', 'service' => 'Snagging', 'call' => 'S01', 'products' => 'No product quantity', 'result' => 'Preserved for review'],
            ],
            'clarification' => [
                'question' => 'Does the fictional call type “CC!” mean Cavity Closers for this example only?',
                'answer' => 'Demonstration response: Yes — apply only to this synthetic example.',
                'safety' => 'This would not create a global alias or reusable business rule.',
            ],
            'totals' => ['Selected records' => 7, 'Windows' => 18, 'Doors' => 2, 'Bifolds' => 1],
            'changes' => [
                '6 proposed additions',
                '1 proposed quantity update',
                '1 source completion observation',
                '0 Portal dates or call-off decisions overwritten',
                'Unmentioned records preserved because this is a partial fictional export',
            ],
            'warnings' => [
                'One fictional call type required an explicit clarification.',
                'No absence is interpreted as deletion.',
                'This preview is precomputed and cannot be committed.',
            ],
        ];
    }
}
