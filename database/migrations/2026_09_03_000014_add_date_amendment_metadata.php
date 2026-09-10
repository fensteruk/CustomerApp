<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_off_date_negotiations', function (Blueprint $table): void {
            $table->date('requested_date')->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->string('reason_label')->nullable();
            $table->foreignId('requested_by_user_id')->nullable();
            $table->foreign('requested_by_user_id', 'negotiation_requester_fk')->references('id')->on('users')->restrictOnDelete();
            $table->string('requester_name')->nullable();
            $table->string('requester_role')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_early_date_exception')->default(false);
            $table->date('normal_earliest_date')->nullable();
            $table->date('resulting_agreed_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('call_off_date_negotiations', function (Blueprint $table): void {
            $table->dropForeign('negotiation_requester_fk');
        });
        Schema::table('call_off_date_negotiations', function (Blueprint $table): void {
            $table->dropColumn(['requested_date', 'reason_code', 'reason_label', 'requested_by_user_id', 'requester_name', 'requester_role', 'is_urgent', 'is_early_date_exception', 'normal_earliest_date', 'resulting_agreed_date']);
        });
    }
};
