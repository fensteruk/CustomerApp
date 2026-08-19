<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_off_batches', function (Blueprint $table): void {
            $table->index(['site_id', 'submitted_at'], 'call_off_batches_site_submitted_index');
        });

        Schema::table('call_off_requests', function (Blueprint $table): void {
            $table->index(['call_off_batch_id', 'trashed_at'], 'call_off_requests_batch_trash_index');
        });
    }

    public function down(): void
    {
        Schema::table('call_off_requests', function (Blueprint $table): void {
            $table->dropIndex('call_off_requests_batch_trash_index');
        });

        Schema::table('call_off_batches', function (Blueprint $table): void {
            $table->dropIndex('call_off_batches_site_submitted_index');
        });
    }
};
