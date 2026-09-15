<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wald_pilot_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::table('wald_pilot_settings')->insert([
            'key' => 'wald_import_pilot_enabled',
            'enabled' => false,
            'lock_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        Schema::create('wald_pilot_setting_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('setting_id')->constrained('wald_pilot_settings')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('actor_role', 40);
            $table->boolean('old_value');
            $table->boolean('new_value');
            $table->text('reason');
            $table->timestamp('created_at');
            $table->index(['setting_id', 'id'], 'wpilot_setting_event_ix');
        });

        Schema::create('wald_pilot_uploads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('stream_id')->constrained('wald_import_streams')->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('uploader_name');
            $table->string('storage_key')->unique();
            $table->string('original_name');
            $table->string('format', 4);
            $table->string('mime', 100);
            $table->unsignedBigInteger('byte_count');
            $table->string('workbook_hash', 64);
            $table->date('export_date');
            $table->string('export_slot', 9);
            $table->string('export_order', 12);
            $table->string('confirmation');
            $table->string('coverage', 40)->default('PARTIAL_FILTERED_EXPORT');
            $table->string('mode', 40)->default('PILOT_SINGLE_SITE_SELECTION');
            $table->json('source_manifest')->nullable();
            $table->string('source_manifest_hash', 64)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('predecessor_upload_id')->nullable()->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->text('replacement_reason')->nullable();
            $table->string('state', 30)->default('UPLOADED');
            $table->unsignedBigInteger('epoch')->default(0);
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('terminal_at')->nullable();
            $table->timestamp('workbook_retain_until');
            $table->timestamps();
            $table->unique(['stream_id', 'export_order', 'revision'], 'wpilot_slot_revision_uq');
            $table->index(['state', 'id'], 'wpilot_state_ix');
        });

        Schema::create('wald_pilot_selections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pilot_upload_id')->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->foreignId('customer_organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('site_id');
            $table->foreign(['site_id', 'customer_organisation_id'], 'wpilot_selection_owner_fk')
                ->references(['id', 'customer_organisation_id'])->on('sites')->restrictOnDelete();
            $table->string('source_identity_kind', 32);
            $table->string('source_identity', 512);
            $table->string('source_identity_hash', 64);
            $table->foreignId('binding_id')->constrained('wald_source_bindings')->restrictOnDelete();
            $table->unsignedInteger('binding_version');
            $table->string('binding_definition_hash', 64);
            $table->unsignedBigInteger('binding_epoch');
            $table->unsignedBigInteger('run_id')->nullable()->unique();
            $table->string('state', 30)->default('UPLOADED');
            $table->timestamps();
            $table->unique(['pilot_upload_id', 'source_identity_hash'], 'wpilot_upload_source_uq');
            $table->unique(['pilot_upload_id', 'site_id'], 'wpilot_upload_site_uq');
        });

        Schema::create('wald_pilot_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pilot_upload_id')->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->foreignId('pilot_selection_id')->nullable()->constrained('wald_pilot_selections')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->uuid('command_uuid');
            $table->string('action', 60);
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->timestamp('created_at');
            $table->unique(['actor_id', 'command_uuid'], 'wpilot_actor_command_uq');
            $table->index(['pilot_upload_id', 'id'], 'wpilot_upload_event_ix');
        });

        Schema::table('wald_import_runs', function (Blueprint $table): void {
            $table->dropUnique(['storage_key']);
            $table->foreignId('pilot_upload_id')->nullable()->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->foreignId('pilot_selection_id')->nullable()->unique()->constrained('wald_pilot_selections')->restrictOnDelete();
            $table->string('source_site_filter_hash', 64)->nullable();
            $table->string('source_site_filter', 512)->nullable();
            $table->string('import_mode', 40)->nullable();
        });
        Schema::table('wald_pilot_selections', function (Blueprint $table): void {
            $table->foreign('run_id')->references('id')->on('wald_import_runs')->restrictOnDelete();
        });
        Schema::table('wald_import_receipts', function (Blueprint $table): void {
            $table->index('stream_id', 'wpilot_receipt_stream_fk_ix');
            $table->dropUnique('w5_slot_revision_uq');
            $table->string('unit_scope_key', 64)->default(str_repeat('0', 64));
            $table->unique(['stream_id', 'export_order', 'revision', 'unit_scope_key'], 'wpilot_unit_slot_revision_uq');
        });

        $this->guard('wald_pilot_uploads', ['uuid', 'stream_id', 'uploader_id', 'uploader_name', 'storage_key', 'original_name', 'format', 'mime', 'byte_count', 'workbook_hash', 'export_date', 'export_slot', 'export_order', 'confirmation', 'coverage', 'mode', 'revision', 'predecessor_upload_id', 'replacement_reason', 'created_at'], 'upload');
        $this->guard('wald_pilot_selections', ['uuid', 'pilot_upload_id', 'customer_organisation_id', 'site_id', 'source_identity_kind', 'source_identity', 'source_identity_hash', 'binding_id', 'binding_version', 'binding_definition_hash', 'binding_epoch', 'created_at'], 'selection');
        $this->updateGuard('wald_import_runs', ['pilot_upload_id', 'pilot_selection_id', 'source_site_filter_hash', 'source_site_filter', 'import_mode'], 'run');
        $this->immutable('wald_pilot_events', 'events');
        $this->updateGuard('wald_pilot_settings', ['key', 'created_at'], 'setting');
        $this->immutable('wald_pilot_setting_events', 'setting_events');
    }

    private function guard(string $table, array $columns, string $name): void
    {
        $this->updateGuard($table, $columns, $name);
        $body = DB::getDriverName() === 'sqlite'
            ? "BEGIN SELECT RAISE(ABORT, 'wald_pilot_identity_immutable'); END"
            : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_pilot_identity_immutable'";
        DB::unprepared("CREATE TRIGGER wpilot_{$name}_delete BEFORE DELETE ON {$table} FOR EACH ROW {$body}");
    }

    private function updateGuard(string $table, array $columns, string $name): void
    {
        $condition = implode(' OR ', array_map(fn (string $column): string => DB::getDriverName() === 'sqlite'
            ? "NEW.{$column} IS NOT OLD.{$column}"
            : "NOT (CAST(NEW.{$column} AS BINARY) <=> CAST(OLD.{$column} AS BINARY))", $columns));
        $body = DB::getDriverName() === 'sqlite'
            ? "WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'wald_pilot_identity_immutable'); END"
            : "BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_pilot_identity_immutable'; END IF; END";
        DB::unprepared("CREATE TRIGGER wpilot_{$name}_identity BEFORE UPDATE ON {$table} FOR EACH ROW {$body}");
    }

    private function immutable(string $table, string $name): void
    {
        foreach (['UPDATE', 'DELETE'] as $operation) {
            $body = DB::getDriverName() === 'sqlite'
                ? "BEGIN SELECT RAISE(ABORT, 'wald_pilot_history_immutable'); END"
                : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_pilot_history_immutable'";
            DB::unprepared("CREATE TRIGGER wpilot_{$name}_".strtolower($operation)." BEFORE {$operation} ON {$table} FOR EACH ROW {$body}");
        }
    }

    public function down(): void
    {
        foreach (['wald_pilot_events', 'wald_pilot_selections', 'wald_pilot_uploads', 'wald_pilot_setting_events'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Refusing rollback of populated Wald pilot records.');
            }
        }
        foreach (['upload_identity', 'upload_delete', 'selection_identity', 'selection_delete', 'run_identity', 'events_update', 'events_delete', 'setting_identity', 'setting_events_update', 'setting_events_delete'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS wpilot_{$trigger}");
        }
        Schema::table('wald_pilot_selections', fn (Blueprint $table) => $table->dropForeign(['run_id']));
        Schema::table('wald_import_receipts', function (Blueprint $table): void {
            $table->dropUnique('wpilot_unit_slot_revision_uq');
            $table->dropColumn('unit_scope_key');
            $table->unique(['stream_id', 'export_order', 'revision'], 'w5_slot_revision_uq');
            $table->dropIndex('wpilot_receipt_stream_fk_ix');
        });
        Schema::table('wald_import_runs', function (Blueprint $table): void {
            $table->dropForeign(['pilot_upload_id']);
            $table->dropForeign(['pilot_selection_id']);
            $table->dropUnique(['pilot_selection_id']);
            $table->dropColumn(['pilot_upload_id', 'pilot_selection_id', 'source_site_filter_hash', 'source_site_filter', 'import_mode']);
            $table->unique('storage_key');
        });
        Schema::drop('wald_pilot_events');
        Schema::drop('wald_pilot_selections');
        Schema::drop('wald_pilot_uploads');
        Schema::drop('wald_pilot_setting_events');
        Schema::drop('wald_pilot_settings');
    }
};
