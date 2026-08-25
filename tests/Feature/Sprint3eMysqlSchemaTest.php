<?php

use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('This release-gate schema inspection requires MySQL.');
    }
});

test('the MySQL release schema has the repaired constraints, indexes and safe identifiers', function (): void {
    $foreignKeys = collect(DB::select(<<<'SQL'
        SELECT table_name, constraint_name, column_name, referenced_table_name, referenced_column_name
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND referenced_table_name IS NOT NULL
        SQL))->mapWithKeys(fn (object $key): array => [$key->constraint_name => [
        'table' => $key->table_name,
        'column' => $key->column_name,
        'references' => $key->referenced_table_name.'.'.$key->referenced_column_name,
    ]]);

    expect($foreignKeys->only([
        'operation_items_operation_fk',
        'operation_items_request_fk',
        'call_off_status_histories_call_off_request_id_foreign',
        'call_off_status_histories_call_off_batch_id_foreign',
        'call_off_status_histories_performed_by_user_id_foreign',
    ])->all())->toBe([
        'operation_items_operation_fk' => ['table' => 'call_off_batch_operation_items', 'column' => 'call_off_batch_operation_id', 'references' => 'call_off_batch_operations.id'],
        'operation_items_request_fk' => ['table' => 'call_off_batch_operation_items', 'column' => 'call_off_request_id', 'references' => 'call_off_requests.id'],
        'call_off_status_histories_call_off_request_id_foreign' => ['table' => 'call_off_status_histories', 'column' => 'call_off_request_id', 'references' => 'call_off_requests.id'],
        'call_off_status_histories_call_off_batch_id_foreign' => ['table' => 'call_off_status_histories', 'column' => 'call_off_batch_id', 'references' => 'call_off_batches.id'],
        'call_off_status_histories_performed_by_user_id_foreign' => ['table' => 'call_off_status_histories', 'column' => 'performed_by_user_id', 'references' => 'users.id'],
    ]);

    $indexes = collect(DB::select(<<<'SQL'
        SELECT table_name, index_name, non_unique,
               GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') AS columns_list
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
        GROUP BY table_name, index_name, non_unique
        SQL));

    $indexNames = $indexes->pluck('index_name')->all();
    expect($indexNames)->toContain(
        'operation_request_unique',
        'call_off_requests_service_status_index',
        'call_off_requests_service_date_index',
        'plot_service_identifier_unique',
        'projected_plot_services_source_call_number_unique',
        'projected_plot_services_source_presence_index',
        'call_off_negotiations_request_lookup_index',
        'negotiation_proposal_sequence_unique',
        'negotiation_proposal_status_index',
    );

    $duplicateIndexes = $indexes
        ->groupBy(fn (object $index): string => implode('|', [$index->table_name, $index->non_unique, $index->columns_list]))
        ->filter(fn ($matching): bool => $matching->count() > 1);
    expect($duplicateIndexes)->toBeEmpty();

    $overlongNames = DB::select(<<<'SQL'
        SELECT constraint_name AS name FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE() AND CHAR_LENGTH(constraint_name) > 64
        UNION ALL
        SELECT index_name AS name FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND CHAR_LENGTH(index_name) > 64
        SQL);
    expect($overlongNames)->toBeEmpty();

    $columns = collect(DB::select(<<<'SQL'
        SELECT table_name, column_name, is_nullable
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND (table_name, column_name) IN (
              ('call_off_requests', 'projected_plot_service_id'),
              ('call_off_requests', 'active_conflict_key'),
              ('call_off_date_proposals', 'responded_by_user_id')
          )
        SQL))->mapWithKeys(fn (object $column): array => ["{$column->table_name}.{$column->column_name}" => $column->is_nullable]);
    expect($columns)->toBe([
        'call_off_requests.projected_plot_service_id' => 'YES',
        'call_off_requests.active_conflict_key' => 'YES',
        'call_off_date_proposals.responded_by_user_id' => 'YES',
    ]);
});
