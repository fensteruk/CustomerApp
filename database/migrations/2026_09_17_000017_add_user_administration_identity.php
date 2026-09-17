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
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->unsignedBigInteger('lock_version')->default(1)->after('is_preview_user');
        });

        DB::table('users')->whereNull('uuid')->orderBy('id')->select('id')->chunkById(250, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('uuid', 'users_uuid_unique');
        });

        $body = DB::getDriverName() === 'sqlite'
            ? "WHEN NEW.uuid IS NULL BEGIN SELECT RAISE(ABORT, 'users_uuid_required'); END"
            : "BEGIN IF NEW.uuid IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'users_uuid_required'; END IF; END";
        DB::unprepared("CREATE TRIGGER users_uuid_required BEFORE INSERT ON users FOR EACH ROW {$body}");
    }

    public function down(): void
    {
        if (DB::table('administrative_audits')->where('entity_type', 'user')->exists()) {
            throw new RuntimeException('Refusing rollback of populated user administration state.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS users_uuid_required');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_uuid_unique');
            $table->dropColumn(['uuid', 'lock_version']);
        });
    }
};
