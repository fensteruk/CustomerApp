<?php

use App\Services\ManualSourceImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('source-import:prune-previews', function (ManualSourceImportService $imports): void {
    $count = $imports->pruneExpired();
    $this->info("Expired manual source import previews pruned: {$count}");
})->purpose('Delete expired private workbook previews and mark their metadata expired');

Schedule::command('source-import:prune-previews')->hourly()->withoutOverlapping();
