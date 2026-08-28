<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
        });
        DB::table('sites')->orderBy('id')->each(function (object $site): void {
            DB::table('sites')->where('id', $site->id)->update(['uuid' => (string) Str::uuid()]);
        }, 250);
        Schema::table('sites', function (Blueprint $table): void {
            $table->unique('uuid', 'sites_uuid_unique');
        });

        Schema::create('source_site_bindings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_namespace');
            $table->string('source_site_key');
            $table->char('source_site_key_hash', 64);
            $table->string('original_name')->nullable();
            $table->string('display_name')->nullable();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['source_namespace', 'source_site_key_hash'], 'source_site_bindings_identity_unique');
            $table->index(['site_id', 'source_namespace'], 'source_site_bindings_site_namespace_index');
        });

        Schema::table('projected_plots', function (Blueprint $table): void {
            $table->foreignId('source_site_binding_id')->nullable()->after('site_id')->constrained()->restrictOnDelete();
            $table->index(['external_source', 'source_site_binding_id'], 'projected_plots_source_binding_index');
        });

        Schema::table('source_import_runs', function (Blueprint $table): void {
            $table->foreignId('initiated_by_user_id')->nullable()->after('source_version')->constrained('users')->nullOnDelete();
            $table->string('original_filename')->nullable()->after('initiated_by_user_id');
            $table->char('content_sha256', 64)->nullable()->after('original_filename');
            $table->json('source_scope')->nullable()->after('content_sha256');
        });

        Schema::create('manual_source_import_previews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('source_namespace');
            $table->string('original_filename');
            $table->char('content_sha256', 64);
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->char('workbook_contract_fingerprint', 64);
            $table->char('source_fingerprint', 64);
            $table->string('status')->index();
            $table->json('metadata');
            $table->json('summary');
            $table->json('rows');
            $table->unsignedInteger('blocking_error_count')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('committed_at')->nullable();
            $table->foreignId('source_import_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['initiated_by_user_id', 'created_at'], 'manual_source_previews_user_time_index');
            $table->index(['source_namespace', 'content_sha256'], 'manual_source_previews_content_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_source_import_previews');

        Schema::table('source_import_runs', function (Blueprint $table): void {
            $table->dropForeign(['initiated_by_user_id']);
            $table->dropColumn(['initiated_by_user_id', 'original_filename', 'content_sha256', 'source_scope']);
        });

        Schema::table('projected_plots', function (Blueprint $table): void {
            $table->dropIndex('projected_plots_source_binding_index');
            $table->dropForeign(['source_site_binding_id']);
            $table->dropColumn('source_site_binding_id');
        });

        Schema::dropIfExists('source_site_bindings');

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropUnique('sites_uuid_unique');
            $table->dropColumn('uuid');
        });
    }
};
