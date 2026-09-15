<?php

return [
    // WALD06 pilot/cutover must explicitly enable the integrated import boundary.
    'enabled' => (bool) env('CUSTOMER_WALD_IMPORT_ENABLED', false),
    // Temporary supervised single-site production pilot. Always opt-in per environment.
    'pilot_available' => (bool) env('WALD_IMPORT_AVAILABLE', false),
];
