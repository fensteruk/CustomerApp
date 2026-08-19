<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_organisations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('portal_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('portal_roles')->insert([
            ['identifier' => 'site_manager', 'name' => 'Site Manager', 'created_at' => now(), 'updated_at' => now()],
            ['identifier' => 'assistant_site_manager', 'name' => 'Assistant Site Manager', 'created_at' => now(), 'updated_at' => now()],
            ['identifier' => 'finishing_foreman', 'name' => 'Finishing Foreman', 'created_at' => now(), 'updated_at' => now()],
            ['identifier' => 'fenster_office_staff', 'name' => 'Fenster Office Staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('customer_organisation_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('portal_role_id')->nullable()->after('customer_organisation_id')->constrained('portal_roles')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('password')->index();
            $table->boolean('is_preview_user')->default(false)->after('is_active')->index();
            $table->index(['customer_organisation_id', 'portal_role_id']);
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_organisation_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->timestamps();

            $table->unique(['customer_organisation_id', 'name']);
            $table->index(['customer_organisation_id', 'name']);
        });

        Schema::create('site_user_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'site_id']);
            $table->index(['site_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_user_assignments');
        Schema::dropIfExists('sites');

        // SQLite cannot safely remove indexed columns in the same schema operation that
        // removes their indexes. Keep these operations separate for rollback support.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['customer_organisation_id']);
            $table->dropForeign(['portal_role_id']);
            $table->dropIndex(['customer_organisation_id', 'portal_role_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_preview_user']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_organisation_id',
                'portal_role_id',
                'is_active',
                'is_preview_user',
            ]);
        });

        Schema::dropIfExists('portal_roles');
        Schema::dropIfExists('customer_organisations');
    }
};
