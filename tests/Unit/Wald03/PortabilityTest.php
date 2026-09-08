<?php

use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use Symfony\Component\Process\Process;

it('replays identical semantic results across fresh database-free processes and timezones', function () {
    $outputs = [];
    for ($run = 0; $run < 3; $run++) {
        $process = new Process([PHP_BINARY, '-n', dirname(__DIR__, 3).'/scripts/verify-wald03-semantics.php']);
        $process->mustRun();
        $outputs[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
    expect($outputs[1])->toBe($outputs[0])->and($outputs[2])->toBe($outputs[0])
        ->and(array_unique($outputs[0]['hashes']))->toHaveCount(1)
        ->and($outputs[0]['forbidden_loaded_classes'])->toBe([])
        ->and($outputs[0]['pdo_drivers'])->toBe([])
        ->and($outputs[0]['composer_loaded'])->toBeFalse()
        ->and($outputs[0]['dictionary']['fingerprint'])->toBe((new CustomerAppDictionary)->identity()->fingerprint);
});

it('keeps semantic runtime free of application infrastructure and external I O', function () {
    $root = dirname(__DIR__, 3).'/app/SourceImport/Semantics';
    $forbidden = ['Illuminate\\', 'App\\Models\\', 'App\\Enums\\', 'config(', 'app(', 'auth(', 'resolve(',
        'file_get_contents(', 'file_put_contents(', 'fopen(', 'curl_', 'PDO', 'DB::', 'dispatch(', 'now(', 'date(', 'time(',
        'OpenAI', 'Anthropic', 'SiteApp\\'];
    $count = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $count++;
        $content = file_get_contents($file->getPathname());
        foreach ($forbidden as $term) {
            expect($content)->not->toContain($term);
        }
    }
    expect($count)->toBe(13);
});
