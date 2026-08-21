<?php

use App\Enums\CallOffServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_name');
            $table->string('source_version')->nullable();
            $table->string('status')->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_applied')->default(0);
            $table->text('safe_error_summary')->nullable();
            $table->timestamps();

            $table->index(['source_name', 'started_at']);
        });

        Schema::create('projected_plot_services', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('projected_plot_id')->constrained()->restrictOnDelete();
            $table->string('service_identifier');
            $table->string('source_call_number')->nullable();
            $table->string('source_call_type')->nullable();
            $table->string('source_job_stage')->nullable();
            $table->date('source_completed_at')->nullable();
            $table->timestamp('source_completion_observed_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_observed_at')->nullable();
            $table->foreignId('last_source_import_run_id')->nullable()->constrained('source_import_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['projected_plot_id', 'service_identifier'], 'plot_service_identifier_unique');
            $table->unique(['source_call_number'], 'projected_plot_services_source_call_number_unique');
            $table->index(['service_identifier', 'source_completed_at'], 'plot_services_service_completion_index');
            $table->index(['source_call_type', 'source_job_stage']);
        });

        Schema::create('projected_plot_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('projected_plot_id')->constrained()->restrictOnDelete();
            $table->string('product_code');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamp('source_updated_at')->nullable();
            $table->foreignId('last_source_import_run_id')->nullable()->constrained('source_import_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['projected_plot_id', 'product_code'], 'plot_product_code_unique');
            $table->index(['projected_plot_id', 'quantity']);
        });

        Schema::table('call_off_requests', function (Blueprint $table): void {
            $table->foreignId('projected_plot_service_id')->nullable()->after('projected_plot_id')->constrained()->restrictOnDelete();
            $table->string('service_identifier')->nullable()->after('projected_plot_service_id');
            $table->date('requested_date')->nullable()->after('service_identifier');
            $table->date('agreed_date')->nullable()->after('requested_date');
            $table->text('customer_response')->nullable()->after('requested_date');
            $table->timestamp('legacy_migrated_at')->nullable()->after('resubmitted_from_call_off_request_id');

            $table->index(['projected_plot_service_id', 'status'], 'call_off_requests_service_status_index');
            $table->index(['service_identifier', 'requested_date'], 'call_off_requests_service_date_index');
        });

        Schema::create('call_off_date_negotiations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_off_request_id')->constrained()->restrictOnDelete();
            $table->string('purpose');
            $table->string('status')->index();
            $table->string('active_negotiation_key')->nullable()->unique();
            $table->date('prior_agreed_date')->nullable();
            $table->text('customer_response')->nullable();
            $table->text('internal_reason')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['call_off_request_id', 'purpose', 'status'], 'call_off_negotiations_request_lookup_index');
        });

        Schema::create('call_off_date_proposals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('call_off_date_negotiation_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('proposal_type');
            $table->string('status')->index();
            $table->date('proposed_date');
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('customer_response')->nullable();
            $table->text('internal_reason')->nullable();
            $table->boolean('is_earlier_date_exception')->default(false);
            $table->timestamp('earlier_date_acknowledged_at')->nullable();
            $table->timestamp('proposed_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['call_off_date_negotiation_id', 'sequence'], 'negotiation_proposal_sequence_unique');
            $table->index(['call_off_date_negotiation_id', 'status'], 'negotiation_proposal_status_index');
            $table->index(['proposed_date', 'status']);
        });

        $this->backfillLegacyPlotServicesAndRequests();
    }

    public function down(): void
    {
        Schema::dropIfExists('call_off_date_proposals');
        Schema::dropIfExists('call_off_date_negotiations');

        Schema::table('call_off_requests', function (Blueprint $table): void {
            $table->dropForeign(['projected_plot_service_id']);
            $table->dropIndex('call_off_requests_service_status_index');
            $table->dropIndex('call_off_requests_service_date_index');
            $table->dropColumn(['projected_plot_service_id', 'service_identifier', 'requested_date', 'agreed_date', 'customer_response', 'legacy_migrated_at']);
        });

        Schema::dropIfExists('projected_plot_products');
        Schema::dropIfExists('projected_plot_services');
        Schema::dropIfExists('source_import_runs');
    }

    private function backfillLegacyPlotServicesAndRequests(): void
    {
        $now = now();

        DB::table('projected_plots')->orderBy('id')->each(function (object $plot) use ($now): void {
            foreach (CallOffServiceType::cases() as $service) {
                DB::table('projected_plot_services')->insertOrIgnore([
                    'uuid' => (string) Str::uuid(),
                    'projected_plot_id' => $plot->id,
                    'service_identifier' => $service->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }, 250);

        DB::table('call_off_requests')
            ->join('call_off_batches', 'call_off_requests.call_off_batch_id', '=', 'call_off_batches.id')
            ->select([
                'call_off_requests.id',
                'call_off_requests.projected_plot_id',
                'call_off_requests.status',
                'call_off_batches.service_identifier',
                'call_off_batches.requested_date',
                'call_off_batches.customer_response',
            ])
            ->orderBy('call_off_requests.id')
            ->each(function (object $request) use ($now): void {
                $serviceId = DB::table('projected_plot_services')
                    ->where('projected_plot_id', $request->projected_plot_id)
                    ->where('service_identifier', $request->service_identifier)
                    ->value('id');

                DB::table('call_off_requests')
                    ->where('id', $request->id)
                    ->update([
                        'projected_plot_service_id' => $serviceId,
                        'service_identifier' => $request->service_identifier,
                        'requested_date' => $request->requested_date,
                        'agreed_date' => $request->status === 'approved' ? $request->requested_date : null,
                        'customer_response' => $request->customer_response,
                        'legacy_migrated_at' => $now,
                    ]);
            }, 250);
    }
};
