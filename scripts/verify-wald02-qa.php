<?php

// SAFE_SYNTHETIC. Standalone QA: deliberately no Composer or Laravel bootstrap.
spl_autoload_register(function (string $class): void {
    foreach (['App\\Wald\\' => '/app/Wald/', 'Tests\\Support\\' => '/tests/Support/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__).$directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        }
    }
});

use App\Wald\Services\AnalysisProblem;
use App\Wald\Services\HeaderDetector;
use App\Wald\Services\Reasoning\ReasoningEngine;
use App\Wald\Services\RegionDetector;
use App\Wald\Services\SheetProfiler;
use App\Wald\Services\ValueProfiler;
use App\Wald\Services\WorkbookProfiler;
use App\Wald\Services\WorkbookSourceFactory;
use Tests\Support\WaldFixtures;

$mode = $argv[1] ?? 'replay';
if (in_array($mode, ['integrity', 'integrity-summary'], true)) {
    $root = $argv[2] ?? throw new RuntimeException('Approved source root required.');
    $manifestPath = $root.'/documentation/wald/siteapp-wald-source-baseline-2026-09-04.md';
    $manifest = file_get_contents($manifestPath);
    preg_match_all('/^\| `([^`]+)` \| [^|]+ \| (\d+) \| `([a-f0-9]{64})` \|/m', $manifest, $matches, PREG_SET_ORDER);
    $inventory = $mismatches = $approved = [];
    foreach ($matches as [, $path, $size, $hash]) {
        $approved[$path] = $hash;
        $inventory[] = "$path|$size|$hash";
        if (! is_file($root.'/'.$path) || filesize($root.'/'.$path) !== (int) $size || hash_file('sha256', $root.'/'.$path) !== $hash) {
            $mismatches[] = $path;
        }
    }
    sort($inventory, SORT_STRING);
    $digest = hash('sha256', implode("\n", $inventory));
    $ledger = file_get_contents(dirname(__DIR__).'/documentation/wald/customer-wald02-portable-core-2026-09-04.md');
    preg_match_all('/^\| `([^`]+)` \| `([a-f0-9]{64})` \| `([^`]+)` \| `ADOPT_AS_IS` \| `IDENTICAL` \|/m', $ledger, $entries, PREG_SET_ORDER);
    $equivalence = [];
    foreach ($entries as [, $source, $hash, $destination]) {
        $original = file_get_contents($root.'/'.$source);
        $adopted = file_get_contents(dirname(__DIR__).'/'.$destination);
        $process = proc_open(['git', 'show', '9980354d28bfe1ca7986e10a529ab073d95d0b91:'.$destination], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $candidate = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Candidate Git object unavailable.');
        }
        $normalise = fn ($value) => str_replace("\r\n", "\n", str_replace(['App\\Wald\\Contracts', 'App\\Wald\\Services'], ['App\\Contracts\\Wald', 'App\\Services\\Wald'], $value));
        $equal = $normalise($original) === $normalise($adopted);
        $documentedFix = in_array($destination, ['app/Wald/Services/XlsxWorkbookSource.php', 'app/Wald/Services/CsvWorkbookSource.php', 'app/Wald/Services/WorkbookProfiler.php'], true);
        $equivalence[] = ['source' => $source, 'destination' => $destination, 'source_hash_matches' => ($approved[$source] ?? '') === $hash && hash('sha256', $original) === $hash,
            'candidate_classification' => $normalise($original) === $normalise($candidate) ? 'IDENTICAL' : 'UNEXPECTED_DIVERGENCE',
            'classification' => $equal ? 'IDENTICAL' : ($documentedFix ? 'INTENTIONALLY_DIVERGED' : 'UNEXPECTED_DIVERGENCE'),
            'qa_sha256' => hash('sha256', $adopted)];
    }
    // The approved digest uses PowerShell Sort-Object path (culture collation),
    // not PHP bytewise ordering. The file hash pins that exact approved manifest.
    echo json_encode(['manifest_file_sha256' => hash_file('sha256', $manifestPath), 'entries' => count($matches), 'bytewise_inventory_digest' => $digest, 'mismatches' => $mismatches, 'ledger_count' => count($entries),
        'candidate_classifications' => array_count_values(array_column($equivalence, 'candidate_classification')),
        'qa_classifications' => array_count_values(array_column($equivalence, 'classification')),
        'equivalence' => $mode === 'integrity-summary' ? array_values(array_filter($equivalence, fn ($entry) => $entry['classification'] !== 'IDENTICAL')) : $equivalence], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(count($matches) === 162 && count($entries) === 57 && $mismatches === [] && hash_file('sha256', $manifestPath) === 'd47cc0f4f11c217b4dce7497e7dad74a874510d4d77de273a772bc377106c392' && count(array_filter($equivalence, fn ($entry) => ! $entry['source_hash_matches'] || $entry['candidate_classification'] !== 'IDENTICAL' || $entry['classification'] === 'UNEXPECTED_DIVERGENCE')) === 0 ? 0 : 1);
}

$values = new ValueProfiler;
$profiler = new WorkbookProfiler(new WorkbookSourceFactory, new SheetProfiler(new RegionDetector($values), new HeaderDetector($values), $values));
try {
    if ($mode === 'observe-csv') {
        try {
            $profile = $profiler->profile($argv[2], 'csv');
            echo json_encode(['complete' => $profile->toArray()['complete']], JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (AnalysisProblem $problem) {
            echo json_encode($problem->toArray(), JSON_THROW_ON_ERROR).PHP_EOL;
        }
    } elseif ($mode === 'benchmark') {
        $count = (int) ($argv[2] ?? 100);
        $rows = [1 => [1 => 'Reference', 2 => 'Category', 3 => 'Amount', 4 => 'When', 5 => 'Description']];
        for ($row = 2; $row <= $count; $row++) {
            $rows[$row] = [1 => 'S'.$row, 2 => 'Group'.($row % 5), 3 => $row % 100, 4 => '2026-09-08', 5 => 'Synthetic observation '.$row];
        }
        $path = WaldFixtures::xlsx([['rows' => $rows]]);
        unset($rows);
        memory_reset_peak_usage();
        $start = hrtime(true);
        $profile = $profiler->profile($path, 'xlsx');
        $profileMs = (hrtime(true) - $start) / 1e6;
        $result = (new ReasoningEngine)->reason($profile)->toArray();
        echo json_encode(['rows' => $count, 'cells' => $count * 5, 'source_bytes' => filesize($path), 'profile_ms' => round($profileMs, 2), 'total_ms' => round((hrtime(true) - $start) / 1e6, 2), 'peak_bytes' => memory_get_peak_usage(true), 'complete' => $profile->toArray()['complete'], 'hypotheses' => count($result['hypotheses'])], JSON_THROW_ON_ERROR).PHP_EOL;
    } else {
        $path = $argv[2] ?? throw new RuntimeException('Synthetic replay path required.');
        $hashes = [];
        foreach (['UTC', 'Europe/London', 'Pacific/Auckland'] as $timezone) {
            date_default_timezone_set($timezone);
            $profile = $profiler->profile($path, 'xlsx');
            $result = (new ReasoningEngine)->reason($profile)->toArray();
            $hashes[] = hash('sha256', json_encode([$profile->toArray(), $result], JSON_THROW_ON_ERROR));
        }
        $forbidden = array_values(array_filter(get_declared_classes(), fn ($class) => str_starts_with($class, 'Illuminate\\') || str_starts_with($class, 'App\\Models\\')));
        echo json_encode(['hashes' => $hashes, 'forbidden_loaded_classes' => $forbidden, 'loaded_files' => count(get_included_files()), 'pdo_drivers' => class_exists('PDO') ? PDO::getAvailableDrivers() : []], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(count(array_unique($hashes)) === 1 && $forbidden === [] ? 0 : 1);
    }
} finally {
    WaldFixtures::cleanup();
}
