<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Existing immutable DELETE guards, replaced only with a transaction-local exception. */
    private const GUARDS = [
        'w4_immutable_0_delete' => ['wald_clarification_answers', 'wald_history_immutable'],
        'w4_immutable_1_delete' => ['wald_profile_versions', 'wald_history_immutable'],
        'w4_immutable_2_delete' => ['wald_knowledge_events', 'wald_history_immutable'],
        'w4_immutable_3_delete' => ['wald_profile_uses', 'wald_history_immutable'],
        'w4_guard_knowledge_contexts_del' => ['wald_knowledge_contexts', 'wald_evidence_immutable'],
        'w4_guard_knowledge_evidence_del' => ['wald_knowledge_evidence', 'wald_evidence_immutable'],
        'w4_guard_clarifications_del' => ['wald_clarifications', 'wald_evidence_immutable'],
        'w4_guard_profiles_del' => ['wald_profiles', 'wald_evidence_immutable'],
        'w5_immutable_0_delete' => ['wald_binding_versions', 'wald_import_history_immutable'],
        'w5_binding_delete' => ['wald_source_bindings', 'wald_binding_identity_immutable'],
        'w5b_immutable_0_delete' => ['wald_import_stages', 'wald_backend_immutable'],
        'w5b_immutable_1_delete' => ['wald_staged_rows', 'wald_backend_immutable'],
        'w5b_immutable_2_delete' => ['wald_import_previews', 'wald_backend_immutable'],
        'w5b_immutable_3_delete' => ['wald_visit_observations', 'wald_backend_immutable'],
        'w5b_immutable_4_delete' => ['wald_import_receipts', 'wald_backend_immutable'],
        'w5b_run_delete' => ['wald_import_runs', 'wald_backend_identity_immutable'],
        'w5b_visit_delete' => ['wald_source_visits', 'wald_backend_identity_immutable'],
        'w5a_0_delete' => ['wald_commit_attempts', 'wald_attempt_immutable'],
        'w5a_1_delete' => ['wald_commit_attempt_outcomes', 'wald_attempt_immutable'],
        'wpilot_selection_delete' => ['wald_pilot_selections', 'wald_pilot_identity_immutable'],
        'wpilot_events_delete' => ['wald_pilot_events', 'wald_pilot_history_immutable'],
        'w6_source_row_delete' => ['wald_source_rows', 'wald_source_row_identity_immutable'],
        'w6_source_row_observation_delete' => ['wald_source_row_observations', 'wald_source_row_history_immutable'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('demo_purge_gate')) {
            Schema::create('demo_purge_gate', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->uuid('entity_uuid');
                $table->timestamp('created_at');
            });
        }

        foreach (self::GUARDS as $name => [$table, $message]) {
            $this->install($name, $table, $message, true);
        }
    }

    public function down(): void
    {
        if (DB::table('demo_purge_gate')->exists()) {
            throw new RuntimeException('Refusing rollback while a demo purge is active.');
        }
        foreach (self::GUARDS as $name => [$table, $message]) {
            $this->install($name, $table, $message, false);
        }
        Schema::drop('demo_purge_gate');
    }

    private function install(string $name, string $table, string $message, bool $gated): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        if (DB::getDriverName() === 'sqlite') {
            $when = $gated ? ' WHEN NOT EXISTS (SELECT 1 FROM demo_purge_gate WHERE id = 1)' : '';
            DB::unprepared("CREATE TRIGGER {$name} BEFORE DELETE ON {$table} FOR EACH ROW{$when} BEGIN SELECT RAISE(ABORT, '{$message}'); END");

            return;
        }
        $body = $gated
            ? "BEGIN IF NOT EXISTS (SELECT 1 FROM demo_purge_gate WHERE id = 1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END"
            : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'";
        DB::unprepared("CREATE TRIGGER {$name} BEFORE DELETE ON {$table} FOR EACH ROW {$body}");
    }
};
