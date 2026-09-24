<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wald_pilot_review_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pilot_upload_id')->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->char('source_identity_hash', 64)->nullable();
            $table->string('customer_code', 100)->nullable();
            $table->string('call_no', 100);
            $table->string('call_type', 100)->nullable();
            $table->string('source_site_name', 512)->nullable();
            $table->longText('raw_plot_ref')->nullable();
            $table->string('parsed_customer', 200)->nullable();
            $table->string('parsed_site', 200)->nullable();
            $table->string('parsed_plot', 200)->nullable();
            $table->string('issue', 80)->nullable();
            $table->string('disposition', 30)->default('ACTIVE');
            $table->foreignId('customer_organisation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('confirmed_plot', 200)->nullable();
            $table->foreignId('decision_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();
            $table->unique(['pilot_upload_id', 'row_number'], 'wpilot_review_row_uq');
            $table->index(['pilot_upload_id', 'source_identity_hash', 'row_number'], 'wpilot_review_group_ix');
            $table->index(['pilot_upload_id', 'disposition', 'row_number'], 'wpilot_review_queue_ix');
        });

        Schema::create('wald_pilot_review_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pilot_review_row_id')->constrained('wald_pilot_review_rows')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('disposition', 30);
            $table->foreignId('customer_organisation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('plot_reference', 200)->nullable();
            $table->string('reason', 100)->nullable();
            $table->timestamp('created_at');
            $table->index(['pilot_review_row_id', 'id'], 'wpilot_review_decision_history_ix');
        });

        Schema::create('wald_pilot_review_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pilot_upload_id')->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->char('source_identity_hash', 64);
            $table->unsignedInteger('version');
            $table->foreignId('customer_organisation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('confirmed_rows');
            $table->unsignedInteger('unknown_rows');
            $table->char('source_manifest_hash', 64);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->timestamp('created_at');
            $table->unique(['pilot_upload_id', 'source_identity_hash', 'version'], 'wpilot_review_group_version_uq');
            $table->index(['pilot_upload_id', 'source_identity_hash', 'id'], 'wpilot_review_group_history_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wald_pilot_review_groups');
        Schema::dropIfExists('wald_pilot_review_decisions');
        Schema::dropIfExists('wald_pilot_review_rows');
    }
};
