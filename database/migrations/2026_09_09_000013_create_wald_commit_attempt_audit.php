<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wald_import_previews', fn (Blueprint $t) => $t->unique(['id', 'run_id'], 'w5a_preview_run_uq'));
        Schema::create('wald_commit_attempts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('run_id')->constrained('wald_import_runs')->restrictOnDelete();
            $t->unsignedBigInteger('preview_id')->nullable();
            $t->foreign(['preview_id', 'run_id'], 'w5a_preview_run_fk')->references(['id', 'run_id'])->on('wald_import_previews')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('command_uuid');
            $t->string('command_hash', 64);
            $t->json('metadata');
            $t->string('metadata_hash', 64);
            $t->timestamp('created_at');
            $t->unique(['actor_id', 'command_uuid'], 'w5a_actor_command_uq');
            $t->index(['run_id', 'id'], 'w5a_run_page_ix');
        });
        Schema::create('wald_commit_attempt_outcomes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->unique()->constrained('wald_commit_attempts')->restrictOnDelete();
            $t->string('outcome', 16);
            $t->string('category', 80);
            $t->foreignId('receipt_id')->nullable()->constrained('wald_import_receipts')->restrictOnDelete();
            $t->timestamp('created_at');
        });
        foreach (['wald_commit_attempts', 'wald_commit_attempt_outcomes'] as $i => $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $body = DB::getDriverName() === 'sqlite' ? "BEGIN SELECT RAISE(ABORT, 'wald_attempt_immutable'); END" : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_attempt_immutable'";
                DB::unprepared('CREATE TRIGGER w5a_'.$i.'_'.strtolower($operation)." BEFORE {$operation} ON {$table} FOR EACH ROW {$body}");
            }
        }
    }

    public function down(): void
    {
        foreach (['wald_commit_attempt_outcomes', 'wald_commit_attempts'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Refusing rollback of populated Wald attempt audit.');
            }
        }
        Schema::drop('wald_commit_attempt_outcomes');
        Schema::drop('wald_commit_attempts');
        Schema::table('wald_import_previews', fn (Blueprint $t) => $t->dropUnique('w5a_preview_run_uq'));
    }
};
