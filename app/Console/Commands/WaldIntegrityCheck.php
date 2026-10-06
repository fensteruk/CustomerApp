<?php

namespace App\Console\Commands;

use App\SourceImport\Integration\BindingIntegrityAudit;
use Illuminate\Console\Command;

final class WaldIntegrityCheck extends Command
{
    protected $signature = 'wald:integrity-check {--json : Output the read-only report as JSON}';

    protected $description = 'Read-only audit of Wald CustomerCode binding integrity';

    public function handle(BindingIntegrityAudit $audit): int
    {
        $report = $audit->run();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->info("{$report['healthy']} healthy; {$report['warnings']} warnings; {$report['blocking']} blocking issues.");
            $this->table(['CustomerCode', 'Customer', 'Site', 'Result', 'Reason'], array_map(
                fn ($item): array => [$item['customer_code'], $item['customer'], $item['site'], $item['category'], $item['reason']], $report['items']));
        }

        return $report['blocking'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
