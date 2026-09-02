<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_source_import_previews', function (Blueprint $table): void {
            $table->string('import_scope')->default('PARTIAL_FILTERED_EXPORT')->after('source_namespace');
            $table->json('complete_site_identifiers')->nullable()->after('import_scope');
        });

        Schema::table('source_import_runs', function (Blueprint $table): void {
            $table->string('import_scope')->default('PARTIAL_FILTERED_EXPORT')->after('source_version');
        });

        Schema::table('projected_plot_services', function (Blueprint $table): void {
            $table->date('source_operational_target_date')->nullable()->after('source_completion_flag');
        });

        Schema::table('workbook_interpretation_profiles', function (Blueprint $table): void {
            $table->string('snapshot_scope')->default('PARTIAL_FILTERED_EXPORT')->change();
        });

        DB::table('workbook_interpretation_profiles')->update([
            'snapshot_scope' => 'PARTIAL_FILTERED_EXPORT',
        ]);
    }

    public function down(): void
    {
        DB::table('workbook_interpretation_profiles')
            ->where('snapshot_scope', 'PARTIAL_FILTERED_EXPORT')
            ->update(['snapshot_scope' => 'represented_sites']);

        Schema::table('workbook_interpretation_profiles', function (Blueprint $table): void {
            $table->string('snapshot_scope')->default('represented_sites')->change();
        });

        Schema::table('projected_plot_services', function (Blueprint $table): void {
            $table->dropColumn('source_operational_target_date');
        });

        Schema::table('source_import_runs', function (Blueprint $table): void {
            $table->dropColumn('import_scope');
        });

        Schema::table('manual_source_import_previews', function (Blueprint $table): void {
            $table->dropColumn(['import_scope', 'complete_site_identifiers']);
        });
    }
};
