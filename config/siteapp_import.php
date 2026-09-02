<?php

return [
    /*
     * Increment this whenever a confirmed source meaning changes. Saved workbook
     * profiles are reusable only within the same semantic version.
     */
    'semantic_version' => 3,

    'call_types' => [
        'PC1' => [
            'description' => 'Plot Install',
            'portal_service' => 'windows',
        ],
        'CC1' => [
            'description' => 'Cavity Closer 1',
            'portal_service' => 'cavity_closers',
        ],
        'CM1' => [
            'description' => 'Revisit 1',
            'portal_service' => 'cml',
        ],
        'CM2' => [
            'description' => 'Revisit 2',
            'portal_service' => 'cml',
        ],
        'CML' => [
            'description' => 'CML Call Off',
            'portal_service' => 'cml',
        ],
    ],

    'likely_call_type_typos' => [
        'CC!' => 'CC1',
    ],

    /*
     * This is the only approved SiteApp product registry. Raw confirmed values may
     * be retained for Office audit, while customer presentation uses group totals.
     */
    'products' => [
        'VS' => ['description' => 'Vertical Slider', 'group' => 'WINDOWS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'TT' => ['description' => 'Tilt and Turn', 'group' => 'WINDOWS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'BAY' => ['description' => 'Bay Window', 'group' => 'WINDOWS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'ALI' => ['description' => 'Aluminium Windows', 'group' => 'WINDOWS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'AOV' => ['description' => 'Automatic Opening Vent Window', 'group' => 'WINDOWS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'FI' => ['description' => 'Fire Window', 'group' => 'WINDOWS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'PSU' => ['description' => 'PVC Door Utility', 'group' => 'DOORS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'PSG' => ['description' => 'PVC Door Garage', 'group' => 'DOORS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'CDF' => ['description' => 'Composite Door Front', 'group' => 'DOORS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'CDU' => ['description' => 'Composite Door Utility', 'group' => 'DOORS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'CDG' => ['description' => 'Composite Door Garage', 'group' => 'DOORS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'PSP' => ['description' => 'PVC Sliding Patio', 'group' => 'DOORS', 'customer_rollup' => true, 'bf_lead_time' => false],
        'BF' => ['description' => 'Bifold', 'group' => 'DOORS', 'customer_rollup' => true, 'bf_lead_time' => true],
        'CAS' => ['description' => 'CAS', 'group' => 'EXCLUDED', 'customer_rollup' => false, 'bf_lead_time' => false],
        'FLU' => ['description' => 'FLU', 'group' => 'EXCLUDED', 'customer_rollup' => false, 'bf_lead_time' => false],
        'PFD' => ['description' => 'PFD', 'group' => 'EXCLUDED', 'customer_rollup' => false, 'bf_lead_time' => false],
        'GLS' => ['description' => 'GLS', 'group' => 'EXCLUDED', 'customer_rollup' => false, 'bf_lead_time' => false],
        'WP' => ['description' => 'WP', 'group' => 'EXCLUDED', 'customer_rollup' => false, 'bf_lead_time' => false],
        'MISC' => ['description' => 'MISC', 'group' => 'EXCLUDED', 'customer_rollup' => false, 'bf_lead_time' => false],
    ],

    'customer_rollups' => [
        'WINDOWS' => 'Total Windows',
        'DOORS' => 'Total Doors',
    ],

    'ignored_fields' => [
        'items ordered status' => 'Items Ordered Status is explicitly excluded from the Portal import.',
    ],

    'unconfirmed_fields' => [
    ],
];
