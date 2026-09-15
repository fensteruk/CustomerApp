<?php

namespace App\SourceImport\Integration;

use App\Models\User;
use App\SourceImport\Knowledge\Canonical;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PilotImportAudit
{
    public function record(User $actor, int $uploadId, string $action, array $payload, ?int $selectionId = null, ?string $command = null): void
    {
        $command ??= (string) Str::uuid();
        $canonical = ['schema' => 'customerapp.wald-pilot-event.v1', 'mode' => 'PILOT_SINGLE_SITE_SELECTION', ...$payload];
        DB::table('wald_pilot_events')->insert([
            'pilot_upload_id' => $uploadId,
            'pilot_selection_id' => $selectionId,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'command_uuid' => $command,
            'action' => $action,
            'payload' => Canonical::json($canonical),
            'payload_hash' => Canonical::hash($canonical),
            'created_at' => now('UTC'),
        ]);
    }
}
