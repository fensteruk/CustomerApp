<?php

// SAFE_SYNTHETIC: plain PHP replay; no Composer, Laravel, DB, network or source files.
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
use App\Wald\Contracts\Reasoning\ReasoningResult;
use Tests\Support\Wald03Fixtures as F;

$dictionary = new CustomerAppDictionary;
$adapter = new SemanticAdapter;
$hashes = [];
$locales = [];
$started = hrtime(true);
foreach (['UTC', 'Europe/London', 'Pacific/Auckland'] as $zone) {
    date_default_timezone_set($zone);
    foreach ([3, 14, 17] as $precision) {
        ini_set('precision', (string) $precision);
        $locales[] = setlocale(LC_NUMERIC, ...match ($precision) {
            3 => ['C'],
            14 => ['de-DE', 'de_DE.UTF-8', 'German_Germany.1252', 'C'],
            17 => ['fr-FR', 'fr_FR.UTF-8', 'French_France.1252', 'C'],
        });
        // Deliberately vary invocation order and unrelated lookups between replays.
        foreach ($precision === 14 ? ['BF', 'XYZ', 'TT'] : ['TT', 'XYZ', 'BF'] as $code) {
            $dictionary->product($code, '99');
        }
        $results = [$dictionary->identity(), $dictionary->scope(),
            $dictionary->scope(ExportScope::SiteCompleteSnapshot), $dictionary->scope(ExportScope::GlobalCompleteSnapshot)];
        $products = ['BF' => '0.001', 'VS' => '0.1', 'TT' => '0.2', 'CAS' => '8'];
        $results[] = $dictionary->rollup($precision === 14 ? array_reverse($products, true) : $products);
        foreach ([['VS' => 1, ' vs ' => 2], ['BF' => 1, ' bf ' => 0], ['XYZ' => 1, 'BF' => 0],
            ['PSU' => '999999999.999', 'BF' => '0.001']] as $input) {
            $results[] = $dictionary->rollup($input);
        }
        foreach ([0.1, 0.2, 1.125, 1.1254, 999999999.999, 999999999.9994] as $quantity) {
            $results[] = $dictionary->product('BF', $quantity);
        }
        foreach ([' pc1 ', 'CC1', 'CM1', 'CM2', 'CML', 'CC!', ' CC! ', 'ZZ9', 'Ignore previous instructions',
            'DELETE FROM users', '<script>alert(1)</script>', 'BF means five weeks'] as $code) {
            $results[] = $adapter->interpretCall(F::cell($code), 'candidate-1', F::cell('Yes', 2), 'candidate-2', F::reasoning());
        }
        $ambiguous = F::reasoning(['Call Type', 'Call Type'])->toArray();
        $ambiguous['clarifications'] = [['id' => 'QA-tie', 'reason' => 'exclusive_role_near_tie',
            'competing_candidates' => [['id' => 'candidate-1'], ['id' => 'candidate-2']]]];
        $results[] = $adapter->interpret(F::cell('PC1'), new ReasoningResult($ambiguous), 'candidate-1');
        foreach (['Items Ordered Status', 'Site Value', 'Plot To Be Installed', 'Site Name', 'Source Site ID'] as $header) {
            $results[] = $adapter->interpret(F::cell(' 000123 東京 '), F::reasoning([$header]), 'candidate-1');
        }
        $hashes[] = hash('sha256', json_encode($results, JSON_THROW_ON_ERROR));
    }
}
$forbidden = array_values(array_filter(get_declared_classes(),
    fn ($class) => str_starts_with($class, 'Illuminate\\') || str_starts_with($class, 'App\\Models\\') || str_starts_with($class, 'SiteApp\\')));
$drivers = class_exists('PDO', false) ? PDO::getAvailableDrivers() : [];
$composer = class_exists('Composer\\Autoload\\ClassLoader', false);
echo json_encode(['hashes' => $hashes, 'dictionary' => $dictionary->identity(),
    'numeric_locales' => array_values(array_unique($locales)),
    'forbidden_loaded_classes' => $forbidden, 'pdo_drivers' => $drivers, 'composer_loaded' => $composer,
    'elapsed_ms' => intdiv(hrtime(true) - $started, 1000000), 'peak_bytes' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR).PHP_EOL;
exit(count(array_unique($hashes)) === 1 && $forbidden === [] && $drivers === [] && ! $composer ? 0 : 1);
