<?php

// SAFE_SYNTHETIC. No Composer, Laravel, database drivers, source files or network needed.
spl_autoload_register(function (string $class): void {
    foreach (['App\\SourceImport\\Semantics\\' => '/app/SourceImport/Semantics/',
        'App\\Wald\\' => '/app/Wald/', 'Tests\\Support\\' => '/tests/Support/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__).$directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        }
    }
});

use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use App\SourceImport\Semantics\Enums\ExportScope;
use App\SourceImport\Semantics\SemanticAdapter;
use Tests\Support\Wald03Fixtures as F;

$hashes = [];
$dictionary = new CustomerAppDictionary;
foreach (['UTC', 'Europe/London', 'Pacific/Auckland'] as $zone) {
    date_default_timezone_set($zone);
    $results = [$dictionary->identity(), $dictionary->scope(), $dictionary->scope(ExportScope::GlobalCompleteSnapshot),
        $dictionary->rollup(['BF' => 2, 'VS' => '0.125', 'TT' => 3, 'CAS' => 8])];
    foreach (['PC1', 'CC1', 'CM1', 'CM2', 'CML', 'CC!', 'ZZ9'] as $code) {
        $results[] = (new SemanticAdapter)->interpretCall(F::cell($code), 'candidate-1', F::cell('Yes', 2), 'candidate-2', F::reasoning());
    }
    $hashes[] = hash('sha256', json_encode($results, JSON_THROW_ON_ERROR));
}
$forbidden = array_values(array_filter(get_declared_classes(),
    fn ($class) => str_starts_with($class, 'Illuminate\\') || str_starts_with($class, 'App\\Models\\')));
$drivers = class_exists('PDO', false) ? PDO::getAvailableDrivers() : [];
echo json_encode(['hashes' => $hashes, 'dictionary' => $dictionary->identity(),
    'forbidden_loaded_classes' => $forbidden, 'pdo_drivers' => $drivers,
    'composer_loaded' => class_exists('Composer\\Autoload\\ClassLoader', false)], JSON_THROW_ON_ERROR).PHP_EOL;
exit(count(array_unique($hashes)) === 1 && $forbidden === [] && $drivers === [] ? 0 : 1);
