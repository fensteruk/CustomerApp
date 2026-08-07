<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('notifiable_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('event_key', 160);
            $table->string('request_uuid', 36);
            $table->string('batch_uuid', 36)->nullable();
            $table->string('site_uuid', 36)->nullable();
            $table->string('site_name');
            $table->string('plot_reference');
            $table->string('service_identifier', 80);
            $table->date('requested_date');
            $table->string('current_status', 40);
            $table->text('customer_response')->nullable();
            $table->string('route_name', 120);
            $table->json('route_parameters');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['notifiable_user_id', 'event_key']);
            $table->index(['notifiable_user_id', 'dismissed_at', 'read_at']);
            $table->index('request_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_notifications');
    }
};
