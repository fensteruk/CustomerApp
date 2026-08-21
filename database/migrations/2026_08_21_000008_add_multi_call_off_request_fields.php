<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_off_requests', function (Blueprint $table): void {
            $table->date('normal_earliest_date')->nullable()->after('requested_date');
            $table->boolean('is_early_date_exception')->default(false)->after('normal_earliest_date');
            $table->text('early_date_reason')->nullable()->after('is_early_date_exception');
            $table->index(['is_early_date_exception', 'requested_date'], 'call_off_requests_early_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('call_off_requests', function (Blueprint $table): void {
            $table->dropIndex('call_off_requests_early_date_index');
            $table->dropColumn(['normal_earliest_date', 'is_early_date_exception', 'early_date_reason']);
        });
    }
};
