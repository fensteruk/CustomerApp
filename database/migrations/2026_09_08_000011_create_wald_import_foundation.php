<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wald_source_bindings', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('identity_hash', 64)->unique();
            $t->string('source_namespace', 80);
            $t->string('identity_kind', 20);
            $t->text('source_identity');
            $t->unsignedInteger('latest_version')->default(0);
            $t->unsignedInteger('active_version')->nullable();
            $t->unsignedInteger('revoked_through')->default(0);
            $t->unsignedBigInteger('epoch')->default(0);
            $t->timestamps();
        });
        Schema::create('wald_binding_versions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('binding_id')->constrained('wald_source_bindings')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->foreignId('customer_organisation_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('site_id');
            $t->foreign(['site_id', 'customer_organisation_id'], 'w5_bind_owner_fk')->references(['id', 'customer_organisation_id'])->on('sites')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->text('reason');
            $t->string('definition_hash', 64);
            $t->timestamp('created_at');
            $t->unique(['binding_id', 'version'], 'w5_binding_version_uq');
        });
        Schema::table('wald_source_bindings', function (Blueprint $t): void {
            $t->foreign(['id', 'active_version'], 'w5_binding_active_fk')->references(['binding_id', 'version'])->on('wald_binding_versions')->restrictOnDelete();
        });
        Schema::create('wald_import_commands', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->string('actor_role', 40);
            $t->uuid('command_uuid');
            $t->string('command_hash', 64);
            $t->string('ability', 40);
            $t->string('scope_hash', 64);
            $t->uuid('subject_uuid')->nullable();
            $t->json('scope');
            $t->json('result');
            $t->json('before_state');
            $t->json('after_state');
            $t->timestamp('created_at');
            $t->timestamp('retain_until');
            $t->unique(['actor_id', 'command_uuid'], 'w5_command_uq');
            $t->index(['scope_hash', 'id'], 'w5_command_audit_idx');
            $t->index(['subject_uuid', 'id'], 'w5_command_subject_idx');
        });
        foreach (['wald_binding_versions', 'wald_import_commands'] as $i => $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $body = DB::getDriverName() === 'sqlite'
                    ? "BEGIN SELECT RAISE(ABORT, 'wald_import_history_immutable'); END"
                    : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_import_history_immutable'";
                DB::unprepared('CREATE TRIGGER w5_immutable_'.$i.'_'.strtolower($operation)." BEFORE {$operation} ON {$table} FOR EACH ROW {$body}");
            }
        }
        $columns = ['uuid', 'identity_hash', 'source_namespace', 'identity_kind', 'source_identity', 'created_at'];
        $condition = implode(' OR ', array_map(fn ($c) => DB::getDriverName() === 'sqlite'
            ? "NEW.{$c} IS NOT OLD.{$c}" : "NOT (CAST(NEW.{$c} AS BINARY) <=> CAST(OLD.{$c} AS BINARY))", $columns));
        $body = DB::getDriverName() === 'sqlite'
            ? "WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'wald_binding_identity_immutable'); END"
            : "BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_binding_identity_immutable'; END IF; END";
        DB::unprepared("CREATE TRIGGER w5_binding_identity BEFORE UPDATE ON wald_source_bindings FOR EACH ROW {$body}");
        $body = DB::getDriverName() === 'sqlite'
            ? "BEGIN SELECT RAISE(ABORT, 'wald_binding_identity_immutable'); END"
            : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_binding_identity_immutable'";
        DB::unprepared("CREATE TRIGGER w5_binding_delete BEFORE DELETE ON wald_source_bindings FOR EACH ROW {$body}");
    }

    public function down(): void
    {
        foreach (['wald_import_commands', 'wald_binding_versions', 'wald_source_bindings'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Refusing rollback of populated Wald import foundation.');
            }
        }
        if (DB::getDriverName() === 'mysql') {
            Schema::table('wald_source_bindings', fn (Blueprint $t) => $t->dropForeign('w5_binding_active_fk'));
        }
        Schema::drop('wald_import_commands');
        Schema::drop('wald_binding_versions');
        Schema::drop('wald_source_bindings');
    }
};
