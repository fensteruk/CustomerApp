<?php

return [
    // WALD06 pilot/cutover must explicitly enable the integrated import boundary.
    'enabled' => (bool) env('CUSTOMER_WALD_IMPORT_ENABLED', false),
];
