<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('projected_plots')->orderBy('id')->each(function (object $plot): void {
            $normalized = preg_replace('/\s+/u', ' ', trim($plot->plot_reference));
            if ($normalized !== $plot->plot_reference) {
                throw new RuntimeException('Cannot establish projected plot identity while non-normalized plot references exist.');
            }
        });
        if (DB::table('projected_plots')
            ->select(['site_id', 'plot_reference'])
            ->groupBy(['site_id', 'plot_reference'])
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Cannot establish projected plot identity while duplicate site/plot references exist.');
        }

        Schema::table('projected_plots', function (Blueprint $table): void {
            $table->unique(['site_id', 'plot_reference'], 'projected_plots_site_plot_unique');
        });

        Schema::create('wald_source_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('identity_hash', 64)->unique();
            $table->string('source_namespace', 80);
            $table->string('call_number', 100);
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('plot_reference');
            $table->unsignedBigInteger('epoch')->default(0);
            $table->unsignedBigInteger('observation_id')->nullable();
            $table->string('export_order', 12);
            $table->string('fact_hash', 64);
            $table->timestamp('created_at');
            $table->index(['site_id', 'plot_reference'], 'w6_source_row_plot_ix');
            $table->unique(['source_namespace', 'call_number'], 'w6_source_row_reference_uq');
        });

        Schema::create('wald_source_row_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_row_id')->constrained('wald_source_rows')->restrictOnDelete();
            $table->foreignId('run_id')->constrained('wald_import_runs')->restrictOnDelete();
            $table->unsignedBigInteger('version');
            $table->json('facts');
            $table->json('provenance');
            $table->string('fact_hash', 64);
            $table->timestamp('created_at');
            $table->timestamp('retain_until');
            $table->unique(['source_row_id', 'version'], 'w6_source_row_version_uq');
        });

        Schema::table('wald_source_visits', function (Blueprint $table): void {
            $table->foreignId('source_row_id')->nullable()->after('source_namespace')->constrained('wald_source_rows')->restrictOnDelete();
            $table->string('call_type', 40)->nullable()->after('call_number');
            $table->unique(['source_row_id', 'call_type'], 'w6_source_row_visit_uq');
        });

        $this->backfillLegacyVisits();
        $this->guardSourceRows();
        $this->guardHistory();
        $this->guardVisitAttachment();
    }

    private function backfillLegacyVisits(): void
    {
        DB::table('wald_source_visits')->orderBy('id')->each(function (object $visit): void {
            $identity = hash('sha256', json_encode([$visit->source_namespace, $visit->call_number], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $sourceRowId = DB::table('wald_source_rows')->insertGetId([
                'identity_hash' => $identity,
                'source_namespace' => $visit->source_namespace,
                'call_number' => $visit->call_number,
                'site_id' => $visit->site_id,
                'plot_reference' => $visit->plot_reference,
                'epoch' => $visit->epoch,
                'export_order' => $visit->export_order,
                'fact_hash' => $visit->fact_hash,
                'created_at' => $visit->created_at,
            ]);

            $latestObservationId = null;
            DB::table('wald_visit_observations')->where('visit_id', $visit->id)->orderBy('version')->each(
                function (object $observation) use ($sourceRowId, &$latestObservationId): void {
                    $latestObservationId = DB::table('wald_source_row_observations')->insertGetId([
                        'source_row_id' => $sourceRowId,
                        'run_id' => $observation->run_id,
                        'version' => $observation->version,
                        'facts' => $observation->facts,
                        'provenance' => $observation->provenance,
                        'fact_hash' => $observation->fact_hash,
                        'created_at' => $observation->created_at,
                        'retain_until' => $observation->retain_until,
                    ]);
                },
            );

            $facts = $visit->observation_id
                ? json_decode((string) DB::table('wald_visit_observations')->where('id', $visit->observation_id)->value('facts'), true)
                : [];
            DB::table('wald_source_rows')->where('id', $sourceRowId)->update(['observation_id' => $latestObservationId]);
            DB::table('wald_source_visits')->where('id', $visit->id)->update([
                'source_row_id' => $sourceRowId,
                'call_type' => $facts['call_type'] ?? null,
            ]);
        });
    }

    private function guardSourceRows(): void
    {
        $columns = ['identity_hash', 'source_namespace', 'call_number', 'site_id', 'plot_reference', 'created_at'];
        $condition = implode(' OR ', array_map(fn (string $column): string => DB::getDriverName() === 'sqlite'
            ? "NEW.{$column} IS NOT OLD.{$column}"
            : "NOT (CAST(NEW.{$column} AS BINARY) <=> CAST(OLD.{$column} AS BINARY))", $columns));
        $body = DB::getDriverName() === 'sqlite'
            ? "WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'wald_source_row_identity_immutable'); END"
            : "BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_source_row_identity_immutable'; END IF; END";
        DB::unprepared("CREATE TRIGGER w6_source_row_identity BEFORE UPDATE ON wald_source_rows FOR EACH ROW {$body}");

        $body = DB::getDriverName() === 'sqlite'
            ? "BEGIN SELECT RAISE(ABORT, 'wald_source_row_identity_immutable'); END"
            : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_source_row_identity_immutable'";
        DB::unprepared("CREATE TRIGGER w6_source_row_delete BEFORE DELETE ON wald_source_rows FOR EACH ROW {$body}");
    }

    private function guardHistory(): void
    {
        foreach (['UPDATE', 'DELETE'] as $operation) {
            $body = DB::getDriverName() === 'sqlite'
                ? "BEGIN SELECT RAISE(ABORT, 'wald_source_row_history_immutable'); END"
                : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_source_row_history_immutable'";
            DB::unprepared('CREATE TRIGGER w6_source_row_observation_'.strtolower($operation)." BEFORE {$operation} ON wald_source_row_observations FOR EACH ROW {$body}");
        }
    }

    private function guardVisitAttachment(): void
    {
        $body = DB::getDriverName() === 'sqlite'
            ? "WHEN (OLD.source_row_id IS NOT NULL AND NEW.source_row_id IS NOT OLD.source_row_id) OR (OLD.call_type IS NOT NULL AND NEW.call_type IS NOT OLD.call_type) BEGIN SELECT RAISE(ABORT, 'wald_visit_source_row_immutable'); END"
            : "BEGIN IF (OLD.source_row_id IS NOT NULL AND NOT (NEW.source_row_id <=> OLD.source_row_id)) OR (OLD.call_type IS NOT NULL AND NOT (NEW.call_type <=> OLD.call_type)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'wald_visit_source_row_immutable'; END IF; END";
        DB::unprepared("CREATE TRIGGER w6_visit_source_row_identity BEFORE UPDATE ON wald_source_visits FOR EACH ROW {$body}");
    }

    public function down(): void
    {
        if (DB::table('wald_source_rows')->exists() || DB::table('wald_source_row_observations')->exists()) {
            throw new RuntimeException('Refusing rollback of populated Wald source-row records.');
        }

        foreach (['w6_source_row_identity', 'w6_source_row_delete', 'w6_source_row_observation_update', 'w6_source_row_observation_delete', 'w6_visit_source_row_identity'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        Schema::table('wald_source_visits', function (Blueprint $table): void {
            $table->dropUnique('w6_source_row_visit_uq');
            $table->dropConstrainedForeignId('source_row_id');
            $table->dropColumn('call_type');
        });
        Schema::drop('wald_source_row_observations');
        Schema::drop('wald_source_rows');
        Schema::table('projected_plots', function (Blueprint $table): void {
            $table->dropUnique('projected_plots_site_plot_unique');
        });
    }
};
