<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projected_plots', function (Blueprint $table): void {
            $table->timestamp('plot_register_established_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projected_plots', fn (Blueprint $table) => $table->dropColumn('plot_register_established_at'));
    }
};
