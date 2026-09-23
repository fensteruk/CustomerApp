<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wald_pilot_hierarchy_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pilot_upload_id')->constrained('wald_pilot_uploads')->restrictOnDelete();
            $table->char('source_identity_hash', 64);
            $table->char('raw_hash', 64);
            $table->string('customer_name', 200);
            $table->string('site_name', 200);
            $table->string('plot_reference', 200);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->timestamp('created_at');
            $table->index(['pilot_upload_id', 'source_identity_hash', 'raw_hash', 'id'], 'wald_hierarchy_answers_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wald_pilot_hierarchy_answers');
    }
};
