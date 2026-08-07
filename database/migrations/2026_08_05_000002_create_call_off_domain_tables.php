<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projected_plots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('external_source');
            $table->string('external_identifier');
            $table->string('plot_reference');
            $table->boolean('is_completed')->default(false)->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synchronised_at')->nullable();
            $table->timestamps();

            $table->unique(['external_source', 'external_identifier']);
            $table->index(['site_id', 'plot_reference']);
        });

        Schema::create('call_off_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('service_identifier');
            $table->date('requested_date');
            $table->text('customer_response')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['site_id', 'service_identifier', 'requested_date']);
            $table->index('submitted_by_user_id');
        });

        Schema::create('call_off_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_off_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('projected_plot_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->string('active_conflict_key')->nullable()->unique();
            $table->timestamp('trashed_at')->nullable()->index();
            $table->timestamp('trash_expires_at')->nullable()->index();
            $table->foreignId('resubmitted_from_call_off_request_id')->nullable()->constrained('call_off_requests')->nullOnDelete();
            $table->timestamps();

            $table->index(['call_off_batch_id', 'status']);
            $table->index(['projected_plot_id', 'status']);
        });

        Schema::create('call_off_batch_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_off_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('performed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('operation_type');
            $table->timestamp('performed_at');
            $table->timestamp('undo_expires_at')->nullable()->index();
            $table->foreignId('reversed_by_operation_id')->nullable()->constrained('call_off_batch_operations')->nullOnDelete();
            $table->timestamps();

            $table->index(['call_off_batch_id', 'operation_type']);
            $table->index('performed_by_user_id');
        });

        Schema::create('call_off_batch_operation_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_off_batch_operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('call_off_request_id')->constrained()->restrictOnDelete();
            $table->json('before_state');
            $table->json('after_state');
            $table->timestamps();

            $table->unique(['call_off_batch_operation_id', 'call_off_request_id'], 'operation_request_unique');
            $table->index('call_off_request_id');
        });

        Schema::create('call_off_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_off_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('call_off_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('call_off_batch_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type');
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('customer_response')->nullable();
            $table->text('internal_reason')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->unique(['call_off_request_id', 'sequence']);
            $table->index(['call_off_batch_id', 'event_type']);
            $table->index('performed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_off_status_histories');
        Schema::dropIfExists('call_off_batch_operation_items');
        Schema::dropIfExists('call_off_batch_operations');
        Schema::dropIfExists('call_off_requests');
        Schema::dropIfExists('call_off_batches');
        Schema::dropIfExists('projected_plots');
    }
};
