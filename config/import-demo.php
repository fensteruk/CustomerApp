<?php

return [
    /*
    | This flag is deliberately off by default. Routes are registered only when it is
    | explicitly enabled in a local or testing environment.
    */
    'enabled' => (bool) env('IMPORT_STUDIO_DEMO_ENABLED', false),
];
