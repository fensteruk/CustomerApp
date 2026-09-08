<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['wald_profile_uses', 'wald_knowledge_events', 'wald_profile_versions', 'wald_profiles',
        'wald_clarification_answers', 'wald_clarifications', 'wald_knowledge_evidence', 'wald_knowledge_contexts'];

    public function up(): void
    {
        Schema::table('sites', fn (Blueprint $t) => $t->unique(['id', 'customer_organisation_id'], 'w4_site_owner_uq'));
        Schema::create('wald_knowledge_contexts', function (Blueprint $t): void {
            $this->base($t);
            $this->scope($t, 'w4_ctx');
            $t->unsignedInteger('generation');
            $t->string('source_checksum', 64);
            $t->string('analysis_hash', 64);
            $t->string('snapshot_hash', 64);
            $t->json('pins');
            $t->string('state', 20)->default('OPEN');
            $t->unsignedInteger('lock_version')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('predecessor_id')->nullable()->constrained('wald_knowledge_contexts', indexName: 'w4_ctx_prev_fk')->restrictOnDelete();
        });
        Schema::create('wald_knowledge_evidence', function (Blueprint $t): void {
            $this->base($t);
            $t->foreignId('context_id')->constrained('wald_knowledge_contexts', indexName: 'w4_ev_ctx_fk')->restrictOnDelete();
            $t->string('payload_hash', 64);
            $t->json('payload');
            $t->string('retention_class', 32);
            $t->timestamp('terminal_at')->nullable();
            $t->timestamp('retain_until')->nullable();
            $t->boolean('on_hold')->default(false);
            $t->unsignedInteger('lock_version')->default(0);
        });
        Schema::create('wald_clarifications', function (Blueprint $t): void {
            $this->base($t);
            $t->foreignId('context_id')->constrained('wald_knowledge_contexts', indexName: 'w4_q_ctx_fk')->restrictOnDelete();
            $t->string('question_key', 100);
            $t->string('question_hash', 64);
            $t->string('type', 20);
            $t->unsignedInteger('sequence')->default(0);
            $t->string('state', 20)->default('OPEN');
            $t->unique(['context_id', 'question_key'], 'w4_q_key_uq');
        });
        Schema::create('wald_clarification_answers', function (Blueprint $t): void {
            $this->base($t);
            $t->foreignId('clarification_id')->constrained('wald_clarifications', indexName: 'w4_ans_q_fk')->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->string('decision', 20);
            $t->string('candidate_id', 64)->nullable();
            $t->boolean('reusable_intent')->default(false);
            $t->foreignId('evidence_id')->constrained('wald_knowledge_evidence', indexName: 'w4_ans_ev_fk')->restrictOnDelete();
            $t->foreignId('predecessor_id')->nullable()->constrained('wald_clarification_answers', indexName: 'w4_ans_prev_fk')->restrictOnDelete();
            $t->unique(['clarification_id', 'sequence'], 'w4_ans_seq_uq');
        });
        Schema::create('wald_profiles', function (Blueprint $t): void {
            $this->base($t);
            $this->scope($t, 'w4_pr');
            $t->string('type', 20)->default('STRUCTURAL');
            $t->string('state', 20)->default('DRAFT');
            $t->unsignedInteger('lock_version')->default(0);
            $t->unsignedInteger('active_version')->nullable();
            $t->timestamp('review_due_at')->nullable();
            $t->timestamp('retired_at')->nullable();
            $t->timestamp('retain_until')->nullable();
        });
        Schema::create('wald_profile_versions', function (Blueprint $t): void {
            $this->base($t);
            $t->foreignId('profile_id')->constrained('wald_profiles', indexName: 'w4_ver_pr_fk')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->json('definition');
            $t->string('definition_hash', 64);
            $t->foreignId('answer_id')->constrained('wald_clarification_answers', indexName: 'w4_ver_ans_fk')->restrictOnDelete();
            $t->unsignedInteger('predecessor_version')->nullable();
            $t->unique(['profile_id', 'version'], 'w4_ver_num_uq');
        });
        Schema::table('wald_profiles', function (Blueprint $t): void {
            $t->foreign(['id', 'active_version'], 'w4_pr_active_fk')->references(['profile_id', 'version'])->on('wald_profile_versions')->restrictOnDelete();
        });
        Schema::create('wald_knowledge_events', function (Blueprint $t): void {
            $this->base($t);
            $this->scope($t, 'w4_evt');
            $t->string('action', 40);
            $t->uuid('command_uuid');
            $t->string('command_hash', 64);
            $t->string('policy_version', 64);
            $t->json('before_state');
            $t->json('after_state');
            $t->uuid('result_uuid');
            $t->foreignId('reason_evidence_id')->nullable()->constrained('wald_knowledge_evidence', indexName: 'w4_evt_ev_fk')->restrictOnDelete();
            $t->unique(['actor_id', 'command_uuid'], 'w4_evt_cmd_uq');
        });
        Schema::create('wald_profile_uses', function (Blueprint $t): void {
            $this->base($t);
            $t->foreignId('context_id')->constrained('wald_knowledge_contexts', indexName: 'w4_use_ctx_fk')->restrictOnDelete();
            $t->foreignId('profile_id')->constrained('wald_profiles', indexName: 'w4_use_pr_fk')->restrictOnDelete();
            $t->unsignedInteger('version')->nullable();
            $t->unsignedInteger('epoch');
            $t->string('evidence_hash', 64);
            $t->string('compatibility', 32);
            $t->boolean('applied');
            $t->string('reason', 80);
            $t->json('pins');
            $t->json('selection')->nullable();
            $t->foreign(['profile_id', 'version'], 'w4_use_ver_fk')->references(['profile_id', 'version'])->on('wald_profile_versions')->restrictOnDelete();
        });
        // Enforce immutable truth even for bulk Eloquent/query-builder writes.
        foreach (['wald_clarification_answers', 'wald_profile_versions', 'wald_knowledge_events', 'wald_profile_uses'] as $i => $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = 'w4_immutable_'.$i.'_'.strtolower($operation);
                $body = DB::getDriverName() === 'sqlite' ? "BEGIN SELECT RAISE(ABORT, 'wald_history_immutable'); END" : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_history_immutable'";
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW {$body}");
            }
        }
        $protected = [
            'wald_knowledge_contexts' => ['uuid', 'actor_id', 'actor_role', 'customer_organisation_id', 'site_id', 'source_namespace', 'workbook_family', 'generation', 'source_checksum', 'analysis_hash', 'snapshot_hash', 'pins', 'predecessor_id', 'created_at'],
            'wald_clarifications' => ['uuid', 'actor_id', 'actor_role', 'context_id', 'question_key', 'question_hash', 'type', 'created_at'],
            'wald_knowledge_evidence' => ['uuid', 'actor_id', 'actor_role', 'context_id', 'payload_hash', 'payload', 'retention_class', 'created_at'],
            'wald_profiles' => ['uuid', 'actor_id', 'actor_role', 'customer_organisation_id', 'site_id', 'source_namespace', 'workbook_family', 'type', 'created_at'],
        ];
        foreach ($protected as $table => $columns) {
            $name = 'w4_guard_'.substr($table, 5);
            if (DB::getDriverName() === 'sqlite') {
                $condition = implode(' OR ', array_map(fn ($c) => "NEW.{$c} IS NOT OLD.{$c}", $columns));
                DB::unprepared("CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} FOR EACH ROW WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'wald_evidence_immutable'); END");
            } else {
                $condition = implode(' OR ', array_map(fn ($c) => "NOT (NEW.{$c} <=> OLD.{$c})", $columns));
                DB::unprepared("CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_evidence_immutable'; END IF; END");
            }
            $body = DB::getDriverName() === 'sqlite' ? "BEGIN SELECT RAISE(ABORT, 'wald_evidence_immutable'); END" : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_evidence_immutable'";
            DB::unprepared("CREATE TRIGGER {$name}_del BEFORE DELETE ON {$table} FOR EACH ROW {$body}");
        }
    }

    private function base(Blueprint $t): void
    {
        $t->id();
        $t->uuid('uuid')->unique();
        $t->foreignId('actor_id')->constrained('users', indexName: substr($t->getTable(), 5).'_actor_fk')->restrictOnDelete();
        $t->string('actor_role', 40);
        $t->timestamp('created_at');
        $t->timestamp('updated_at');
    }

    private function scope(Blueprint $t, string $prefix): void
    {
        $t->foreignId('customer_organisation_id')->constrained('customer_organisations', indexName: $prefix.'_org_fk')->restrictOnDelete();
        $t->unsignedBigInteger('site_id');
        $t->string('source_namespace', 80);
        $t->string('workbook_family', 80);
        $t->foreign(['site_id', 'customer_organisation_id'], $prefix.'_site_fk')->references(['id', 'customer_organisation_id'])->on('sites')->restrictOnDelete();
        $t->index(['customer_organisation_id', 'site_id', 'source_namespace', 'workbook_family'], $prefix.'_scope_idx');
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Refusing rollback of populated Wald knowledge/audit.');
            }
        }
        Schema::table('wald_profiles', fn (Blueprint $t) => $t->dropForeign('w4_pr_active_fk'));
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('sites', fn (Blueprint $t) => $t->dropUnique('w4_site_owner_uq'));
    }
};
