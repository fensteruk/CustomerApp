<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wald_pilot_ignored_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pilot_upload_id')->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('call_no', 100);
            $table->string('customer_code', 512)->nullable();
            $table->string('call_type', 100)->nullable();
            $table->text('plot_ref')->nullable();
            $table->string('reason', 40);
            $table->string('disposition', 30)->default('IGNORED');
            $table->foreignId('decision_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();
            $table->unique(['pilot_upload_id', 'row_number'], 'wpilot_ignored_row_uq');
            $table->index(['pilot_upload_id', 'disposition', 'row_number'], 'wpilot_ignored_listing_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wald_pilot_ignored_rows');
    }
};
