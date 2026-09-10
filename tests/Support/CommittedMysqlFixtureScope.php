<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Test-only, serial ownership boundary for committed multi-process fixtures.
 * Keep pre-existing IDs/rows; remove only IDs created since this scope began.
 * Child-first deletes retain FK enforcement and never reset reference data.
 */
final class CommittedMysqlFixtureScope
{
    private const TABLES = [
        'source_projection_events', 'source_projection_issues', 'portal_notifications',
        'call_off_status_histories', 'call_off_batch_operation_items',
        'call_off_date_proposals', 'call_off_date_negotiations', 'call_off_batch_operations',
        'call_off_requests', 'call_off_batches', 'projected_plot_products',
        'projected_plot_services', 'projected_plots', 'site_user_assignments',
        'sites', 'users', 'customer_organisations', 'source_import_runs', 'portal_roles',
    ];

    private array $before;

    public function __construct()
    {
        self::assertSafe();
        $this->before = $this->snapshot();
    }

    public static function assertSafe(): void
    {
        if (! app()->environment('testing')
            || DB::connection()->getDriverName() !== 'mysql'
            || ! preg_match('/^(customerapp_test|portal_sprint3f_gate_[a-z0-9_]+)$/', DB::connection()->getDatabaseName())
            || DB::transactionLevel() !== 0) {
            throw new RuntimeException('Committed fixtures require a dedicated disposable testing MySQL database without a parent transaction.');
        }
    }

    public function cleanup(): void
    {
        self::assertSafe();
        DB::transaction(function (): void {
            foreach (self::TABLES as $table) {
                $createdIds = array_values(array_diff(
                    DB::table($table)->pluck('id')->all(), array_keys($this->before[$table]),
                ));
                // Concrete captured IDs only: no truncation, reset, cascade bypass or prefix delete.
                foreach (array_chunk($createdIds, 250) as $ids) {
                    DB::table($table)->whereIn('id', $ids)->delete();
                }
            }
        });
        Assert::assertSame($this->before, $this->snapshot(), 'Committed fixture cleanup must restore every baseline row, including reference data.');
    }

    private function snapshot(): array
    {
        $rows = [];
        foreach (self::TABLES as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()
                ->mapWithKeys(fn ($row): array => [$row->id => (array) $row])->all();
        }

        return $rows;
    }
}
