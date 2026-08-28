<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workbook_interpretation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_namespace');
            $table->string('sheet_identifier');
            $table->char('structural_fingerprint', 64);
            $table->json('normalised_headers');
            $table->json('type_profile');
            $table->json('confirmed_mappings');
            $table->string('snapshot_scope')->default('represented_sites');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('confirmed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['source_namespace', 'structural_fingerprint', 'version'], 'workbook_profiles_fingerprint_version_unique');
            $table->index(['source_namespace', 'sheet_identifier', 'created_at'], 'workbook_profiles_match_index');
        });

        Schema::table('manual_source_import_previews', function (Blueprint $table): void {
            $table->foreignId('workbook_interpretation_profile_id')->nullable()->after('workbook_contract_fingerprint')->constrained()->nullOnDelete();
            $table->json('workbook_interpretation')->nullable()->after('workbook_interpretation_profile_id');
            $table->json('confirmed_mapping')->nullable()->after('workbook_interpretation');
            $table->foreignId('mapping_confirmed_by_user_id')->nullable()->after('confirmed_mapping')->constrained('users')->nullOnDelete();
            $table->timestamp('mapping_confirmed_at')->nullable()->after('mapping_confirmed_by_user_id');
        });

        Schema::table('projected_plot_services', function (Blueprint $table): void {
            $table->boolean('source_completion_flag')->nullable()->after('source_job_stage');
        });
    }

    public function down(): void
    {
        Schema::table('projected_plot_services', function (Blueprint $table): void {
            $table->dropColumn('source_completion_flag');
        });

        Schema::table('manual_source_import_previews', function (Blueprint $table): void {
            $table->dropForeign(['workbook_interpretation_profile_id']);
            $table->dropForeign(['mapping_confirmed_by_user_id']);
            $table->dropColumn([
                'workbook_interpretation_profile_id',
                'workbook_interpretation',
                'confirmed_mapping',
                'mapping_confirmed_by_user_id',
                'mapping_confirmed_at',
            ]);
        });

        Schema::dropIfExists('workbook_interpretation_profiles');
    }
};
