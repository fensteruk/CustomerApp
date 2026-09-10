<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_organisations', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('name')->index();
            $table->unsignedBigInteger('lock_version')->default(1)->after('is_active');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('external_identifier')->index();
            $table->unsignedBigInteger('lock_version')->default(1)->after('is_active');
        });

        $this->backfillUuids('customer_organisations');
        $this->backfillUuids('sites');

        Schema::table('customer_organisations', function (Blueprint $table): void {
            $table->unique('uuid', 'customer_organisations_uuid_unique');
            $table->index(['is_active', 'name'], 'customer_organisations_active_name_index');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->unique('uuid', 'sites_uuid_unique');
            $table->index(['customer_organisation_id', 'is_active', 'name'], 'sites_owner_active_name_index');
        });

        Schema::create('administrative_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('actor_role', 40);
            $table->string('entity_type', 40);
            $table->uuid('entity_uuid');
            $table->string('action', 40);
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            $table->index(['entity_type', 'entity_uuid', 'id'], 'admin_audits_entity_index');
            $table->index(['actor_user_id', 'id'], 'admin_audits_actor_index');
        });

        foreach (['UPDATE', 'DELETE'] as $operation) {
            $body = DB::getDriverName() === 'sqlite'
                ? "BEGIN SELECT RAISE(ABORT, 'administrative_audit_immutable'); END"
                : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'administrative_audit_immutable'";

            DB::unprepared('CREATE TRIGGER administrative_audits_'.strtolower($operation)
                ." BEFORE {$operation} ON administrative_audits FOR EACH ROW {$body}");
        }

        $this->guardUuid('customer_organisations', 'customer_organisations_uuid_required');
        $this->guardUuid('sites', 'sites_uuid_required');
    }

    public function down(): void
    {
        if (DB::table('administrative_audits')->exists()
            || DB::table('customer_organisations')->where(fn ($query) => $query
                ->where('is_active', false)
                ->orWhere('lock_version', '>', 1))->exists()
            || DB::table('sites')->where(fn ($query) => $query
                ->where('is_active', false)
                ->orWhere('lock_version', '>', 1))->exists()) {
            throw new RuntimeException('Refusing rollback of populated Customer/Site administration state.');
        }

        foreach ([
            'administrative_audits_update',
            'administrative_audits_delete',
            'customer_organisations_uuid_required',
            'sites_uuid_required',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        Schema::drop('administrative_audits');

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropUnique('sites_uuid_unique');
            $table->dropIndex('sites_owner_active_name_index');
            $table->dropIndex(['is_active']);
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['uuid', 'is_active', 'lock_version']);
        });

        Schema::table('customer_organisations', function (Blueprint $table): void {
            $table->dropUnique('customer_organisations_uuid_unique');
            $table->dropIndex('customer_organisations_active_name_index');
            $table->dropIndex(['is_active']);
        });

        Schema::table('customer_organisations', function (Blueprint $table): void {
            $table->dropColumn(['uuid', 'is_active', 'lock_version']);
        });
    }

    private function backfillUuids(string $table): void
    {
        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(250, function ($records) use ($table): void {
                foreach ($records as $record) {
                    DB::table($table)->where('id', $record->id)->update([
                        'uuid' => (string) Str::uuid(),
                    ]);
                }
            });
    }

    private function guardUuid(string $table, string $trigger): void
    {
        $body = DB::getDriverName() === 'sqlite'
            ? "WHEN NEW.uuid IS NULL BEGIN SELECT RAISE(ABORT, '{$trigger}'); END"
            : "BEGIN IF NEW.uuid IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$trigger}'; END IF; END";

        DB::unprepared("CREATE TRIGGER {$trigger} BEFORE INSERT ON {$table} FOR EACH ROW {$body}");
    }
};
