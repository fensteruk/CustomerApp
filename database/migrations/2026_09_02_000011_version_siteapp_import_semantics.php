<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workbook_interpretation_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('semantic_version')->default(1)->after('source_namespace');
            $table->index(['source_namespace', 'semantic_version', 'created_at'], 'workbook_profiles_semantic_match_index');
        });
    }

    public function down(): void
    {
        Schema::table('workbook_interpretation_profiles', function (Blueprint $table): void {
            $table->dropIndex('workbook_profiles_semantic_match_index');
            $table->dropColumn('semantic_version');
        });
    }
};
