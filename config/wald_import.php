<?php

use Illuminate\Support\Env;

$waldImportAvailable = Env::getRepository()->get('WALD_IMPORT_AVAILABLE');

return [
    // WALD06 pilot/cutover must explicitly enable the integrated import boundary.
    'enabled' => (bool) env('CUSTOMER_WALD_IMPORT_ENABLED', false),
    // Emergency gate: only the exact case-insensitive word "true" opts in.
    'pilot_available' => is_string($waldImportAvailable) && strtolower($waldImportAvailable) === 'true',
    // Separate fail-closed switch; paired Analyse does not enable the pilot Apply flow.
    'paired_analyse_available' => is_string(Env::getRepository()->get('WALD_PAIRED_ANALYSE_AVAILABLE'))
        && strtolower(Env::getRepository()->get('WALD_PAIRED_ANALYSE_AVAILABLE')) === 'true',
];
