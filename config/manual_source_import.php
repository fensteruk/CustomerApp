<?php

return [
    'source_namespace' => 'siteapp-xlsx',
    'max_upload_kilobytes' => 10 * 1024,
    'preview_ttl_minutes' => 30,
    'max_rows' => 5000,
    'storage_disk' => 'local',
    'storage_directory' => 'manual-source-imports/previews',

    /*
     * These values intentionally have no guessed defaults. They must be filled from the
     * representative operational workbook before the transport can accept an import.
     */
    'workbook' => [
        'worksheet' => env('SITEAPP_XLSX_WORKSHEET'),
        'header_row' => (int) env('SITEAPP_XLSX_HEADER_ROW', 1),
        'date_system' => env('SITEAPP_XLSX_DATE_SYSTEM'),
        'headers' => [
            'call_number' => env('SITEAPP_XLSX_HEADER_CALL_NUMBER'),
            'source_site_key' => env('SITEAPP_XLSX_HEADER_SITE_KEY'),
            'source_site_name' => env('SITEAPP_XLSX_HEADER_SITE_NAME'),
            'plot_reference' => env('SITEAPP_XLSX_HEADER_PLOT_REFERENCE'),
            'call_type' => env('SITEAPP_XLSX_HEADER_CALL_TYPE'),
            'job_stage' => env('SITEAPP_XLSX_HEADER_JOB_STAGE'),
            'completed_date' => env('SITEAPP_XLSX_HEADER_COMPLETED_DATE'),
            'plot_to_be_installed' => env('SITEAPP_XLSX_HEADER_PLOT_TO_BE_INSTALLED'),
            'source_updated_at' => env('SITEAPP_XLSX_HEADER_SOURCE_UPDATED_AT'),
        ],
        'product_headers' => array_values(array_filter(array_map(
            static fn (string $header): string => trim($header),
            explode(',', (string) env('SITEAPP_XLSX_PRODUCT_HEADERS', '')),
        ))),
    ],
];
