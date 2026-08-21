<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('external_source')->nullable()->after('location');
            $table->string('external_identifier')->nullable()->after('external_source');
            $table->unique(['external_source', 'external_identifier'], 'sites_source_identity_unique');
        });

        Schema::table('source_import_runs', function (Blueprint $table): void {
            $table->unsignedInteger('records_created')->default(0)->after('records_applied');
            $table->unsignedInteger('records_updated')->default(0)->after('records_created');
            $table->unsignedInteger('records_unchanged')->default(0)->after('records_updated');
            $table->unsignedInteger('records_missing')->default(0)->after('records_unchanged');
            $table->unsignedInteger('records_rejected')->default(0)->after('records_missing');
            $table->unsignedInteger('reconciliation_issue_count')->default(0)->after('records_rejected');
        });

        Schema::table('projected_plot_services', function (Blueprint $table): void {
            $table->boolean('source_present')->default(true)->after('last_observed_at');
            $table->timestamp('source_missing_since')->nullable()->after('source_present');
            $table->index(['source_present', 'last_observed_at'], 'projected_plot_services_source_presence_index');
        });

        Schema::create('source_projection_issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_import_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('projected_plot_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('issue_key')->unique();
            $table->string('issue_type')->index();
            $table->string('source_call_number')->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['issue_type', 'resolved_at']);
        });

        Schema::create('source_projection_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_import_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('projected_plot_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('call_off_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['projected_plot_service_id', 'occurred_at'], 'source_projection_events_service_time_index');
            $table->index(['call_off_request_id', 'occurred_at'], 'source_projection_events_request_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_projection_events');
        Schema::dropIfExists('source_projection_issues');

        Schema::table('projected_plot_services', function (Blueprint $table): void {
            $table->dropIndex('projected_plot_services_source_presence_index');
            $table->dropColumn(['source_present', 'source_missing_since']);
        });

        Schema::table('source_import_runs', function (Blueprint $table): void {
            $table->dropColumn(['records_created', 'records_updated', 'records_unchanged', 'records_missing', 'records_rejected', 'reconciliation_issue_count']);
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropUnique('sites_source_identity_unique');
            $table->dropColumn(['external_source', 'external_identifier']);
        });
    }
};
