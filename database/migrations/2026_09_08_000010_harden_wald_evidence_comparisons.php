<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $protected = [
        'wald_knowledge_contexts' => ['uuid', 'actor_id', 'actor_role', 'customer_organisation_id', 'site_id', 'source_namespace', 'workbook_family', 'generation', 'source_checksum', 'analysis_hash', 'snapshot_hash', 'pins', 'predecessor_id', 'created_at'],
        'wald_clarifications' => ['uuid', 'actor_id', 'actor_role', 'context_id', 'question_key', 'question_hash', 'type', 'created_at'],
        'wald_knowledge_evidence' => ['uuid', 'actor_id', 'actor_role', 'context_id', 'payload_hash', 'payload', 'retention_class', 'created_at'],
        'wald_profiles' => ['uuid', 'actor_id', 'actor_role', 'customer_organisation_id', 'site_id', 'source_namespace', 'workbook_family', 'type', 'created_at'],
    ];

    public function up(): void
    {
        $this->replaceGuards(true);
    }

    public function down(): void
    {
        // Never weaken protection for retained knowledge, even though this migration drops no data.
        foreach ([...array_keys($this->protected), 'wald_clarification_answers', 'wald_profile_versions', 'wald_knowledge_events', 'wald_profile_uses'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Refusing rollback of populated Wald evidence guards.');
            }
        }
        $this->replaceGuards(false);
    }

    private function replaceGuards(bool $binary): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite IS NOT already compares these values exactly.
        }
        foreach ($this->protected as $table => $columns) {
            $name = 'w4_guard_'.substr($table, 5);
            $condition = implode(' OR ', array_map(fn ($column) => $binary
                ? "NOT (CAST(NEW.{$column} AS BINARY) <=> CAST(OLD.{$column} AS BINARY))"
                : "NOT (NEW.{$column} <=> OLD.{$column})", $columns));
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared("CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_evidence_immutable'; END IF; END");
        }
    }
};
