<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['wald_import_receipts', 'wald_visit_observations', 'wald_source_visits', 'wald_import_previews', 'wald_staged_rows', 'wald_import_stages', 'wald_import_runs', 'wald_import_streams'];

    public function up(): void
    {
        Schema::create('wald_import_streams', function (Blueprint $t) {
            $t->id();
            $t->string('identity_hash', 64)->unique();
            $t->string('source_namespace', 80);
            $t->string('workbook_family', 80);
            $t->unsignedBigInteger('epoch')->default(0);
            $t->string('latest_order', 12)->nullable();
        });
        Schema::create('wald_import_runs', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('stream_id')->constrained('wald_import_streams')->restrictOnDelete();
            $t->foreignId('customer_organisation_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('site_id');
            $t->foreign(['site_id', 'customer_organisation_id'], 'w5_run_owner_fk')->references(['id', 'customer_organisation_id'])->on('sites')->restrictOnDelete();
            $t->string('source_namespace', 80);
            $t->string('workbook_family', 80);
            $t->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $t->string('uploader_name');
            $t->string('storage_key')->unique();
            $t->string('original_name');
            $t->string('format', 4);
            $t->string('mime', 100);
            $t->unsignedBigInteger('byte_count');
            $t->string('workbook_hash', 64);
            $t->date('export_date');
            $t->string('export_slot', 9);
            $t->string('export_order', 12);
            $t->string('confirmation');
            $t->string('provenance', 20);
            $t->string('coverage', 40);
            $t->foreignId('predecessor_id')->nullable()->constrained('wald_import_runs')->restrictOnDelete();
            $t->text('replacement_reason')->nullable();
            $t->string('state', 30)->default('UPLOADED');
            $t->unsignedBigInteger('epoch')->default(0);
            $t->unsignedInteger('generation')->default(0);
            $t->unsignedInteger('attempts')->default(0);
            $t->uuid('lease_token')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->foreignId('context_id')->nullable()->constrained('wald_knowledge_contexts')->restrictOnDelete();
            $t->unsignedBigInteger('stage_id')->nullable();
            $t->unsignedBigInteger('preview_id')->nullable();
            $t->string('failure_code', 80)->nullable();
            $t->timestamp('terminal_at')->nullable();
            $t->timestamp('workbook_retain_until')->nullable();
            $t->boolean('on_hold')->default(false);
            $t->timestamps();
            $t->index(['site_id', 'id']);
            $t->index(['state', 'lease_until']);
        });
        Schema::create('wald_import_stages', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('run_id')->constrained('wald_import_runs')->restrictOnDelete();
            $t->unsignedInteger('generation');
            $t->json('manifest');
            $t->string('manifest_hash', 64);
            $t->string('canonical_hash', 64);
            $t->unsignedInteger('row_count');
            $t->unsignedInteger('blocked_count');
            $t->timestamp('created_at');
            $t->timestamp('retain_until');
            $t->unique(['run_id', 'generation']);
        });
        Schema::create('wald_staged_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stage_id')->constrained('wald_import_stages')->restrictOnDelete();
            $t->unsignedInteger('ordinal');
            $t->json('payload');
            $t->string('payload_hash', 64);
            $t->unique(['stage_id', 'ordinal']);
        });
        Schema::create('wald_import_previews', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('run_id')->constrained('wald_import_runs')->restrictOnDelete();
            $t->foreignId('stage_id')->constrained('wald_import_stages')->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->string('reviewer_name');
            $t->json('payload');
            $t->string('payload_hash', 64);
            $t->timestamp('created_at');
            $t->timestamp('expires_at');
            $t->timestamp('retain_until');
        });
        Schema::create('wald_source_visits', function (Blueprint $t) {
            $t->id();
            $t->string('identity_hash', 64)->unique();
            $t->string('source_namespace', 80);
            $t->string('call_number', 100);
            $t->foreignId('site_id')->constrained()->restrictOnDelete();
            $t->string('plot_reference');
            $t->string('service_identifier', 40);
            $t->foreignId('projected_plot_service_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('epoch')->default(0);
            $t->unsignedBigInteger('observation_id')->nullable();
            $t->string('export_order', 12);
            $t->string('fact_hash', 64);
            $t->timestamp('created_at');
        });
        Schema::create('wald_visit_observations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained('wald_source_visits')->restrictOnDelete();
            $t->foreignId('run_id')->constrained('wald_import_runs')->restrictOnDelete();
            $t->unsignedBigInteger('version');
            $t->json('facts');
            $t->json('provenance');
            $t->string('fact_hash', 64);
            $t->timestamp('created_at');
            $t->timestamp('retain_until');
            $t->unique(['visit_id', 'version']);
        });
        Schema::create('wald_import_receipts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('run_id')->unique()->constrained('wald_import_runs')->restrictOnDelete();
            $t->foreignId('preview_id')->constrained('wald_import_previews')->restrictOnDelete();
            $t->foreignId('stream_id')->constrained('wald_import_streams')->restrictOnDelete();
            $t->string('export_order', 12);
            $t->unsignedInteger('revision');
            $t->string('canonical_hash', 64);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name');
            $t->json('payload');
            $t->string('payload_hash', 64);
            $t->timestamp('created_at');
            $t->timestamp('retain_until');
            $t->unique(['stream_id', 'export_order', 'revision'], 'w5_slot_revision_uq');
        });
        foreach (['wald_import_stages', 'wald_staged_rows', 'wald_import_previews', 'wald_visit_observations', 'wald_import_receipts'] as $i => $table) {
            foreach (['UPDATE', 'DELETE'] as $op) {
                $body = DB::getDriverName() === 'sqlite' ? "BEGIN SELECT RAISE(ABORT, 'wald_backend_immutable'); END" : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_backend_immutable'";
                DB::unprepared('CREATE TRIGGER w5b_immutable_'.$i.'_'.strtolower($op)." BEFORE {$op} ON {$table} FOR EACH ROW {$body}");
            }
        }
        $this->guard('wald_import_runs', ['uuid', 'stream_id', 'customer_organisation_id', 'site_id', 'source_namespace', 'workbook_family', 'uploader_id', 'uploader_name', 'storage_key', 'original_name', 'format', 'mime', 'byte_count', 'workbook_hash', 'export_date', 'export_slot', 'export_order', 'confirmation', 'provenance', 'coverage', 'predecessor_id', 'replacement_reason', 'created_at'], 'run');
        $this->guard('wald_source_visits', ['identity_hash', 'source_namespace', 'call_number', 'site_id', 'plot_reference', 'service_identifier', 'projected_plot_service_id', 'created_at'], 'visit');
        $this->guard('wald_import_streams', ['identity_hash', 'source_namespace', 'workbook_family'], 'stream');
        foreach (['projected_plots', 'projected_plot_services', 'projected_plot_products'] as $i => $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('wald_epoch')->default(0));
            $sql = DB::getDriverName() === 'sqlite'
                ? "AFTER UPDATE ON {$table} FOR EACH ROW WHEN NEW.wald_epoch <= OLD.wald_epoch BEGIN UPDATE {$table} SET wald_epoch = OLD.wald_epoch + 1 WHERE id = NEW.id; END"
                : "BEFORE UPDATE ON {$table} FOR EACH ROW SET NEW.wald_epoch = OLD.wald_epoch + 1";
            DB::unprepared("CREATE TRIGGER w5b_projection_epoch_{$i} {$sql}");
        }
    }

    private function guard(string $table, array $columns, string $suffix): void
    {
        $condition = implode(' OR ', array_map(fn ($c) => DB::getDriverName() === 'sqlite' ? "NEW.{$c} IS NOT OLD.{$c}" : "NOT (CAST(NEW.{$c} AS BINARY) <=> CAST(OLD.{$c} AS BINARY))", $columns));
        $body = DB::getDriverName() === 'sqlite' ? "WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'wald_backend_identity_immutable'); END" : "BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_backend_identity_immutable'; END IF; END";
        DB::unprepared("CREATE TRIGGER w5b_{$suffix}_identity BEFORE UPDATE ON {$table} FOR EACH ROW {$body}");
        $body = DB::getDriverName() === 'sqlite' ? "BEGIN SELECT RAISE(ABORT, 'wald_backend_identity_immutable'); END" : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_backend_identity_immutable'";
        DB::unprepared("CREATE TRIGGER w5b_{$suffix}_delete BEFORE DELETE ON {$table} FOR EACH ROW {$body}");
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Refusing rollback of populated Wald backend.');
            }
        }
        foreach (['projected_plots', 'projected_plot_services', 'projected_plot_products'] as $i => $table) {
            DB::unprepared("DROP TRIGGER w5b_projection_epoch_{$i}");
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('wald_epoch'));
        }
        foreach ($this->tables as $table) {
            Schema::drop($table);
        }
    }
};
