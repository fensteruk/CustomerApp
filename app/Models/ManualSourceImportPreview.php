<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualSourceImportPreview extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'initiated_by_user_id',
        'source_namespace',
        'original_filename',
        'content_sha256',
        'storage_disk',
        'storage_path',
        'workbook_contract_fingerprint',
        'source_fingerprint',
        'status',
        'metadata',
        'summary',
        'rows',
        'blocking_error_count',
        'expires_at',
        'committed_at',
        'source_import_run_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'summary' => 'array',
            'rows' => 'array',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /** @return BelongsTo<SourceImportRun, $this> */
    public function importRun(): BelongsTo
    {
        return $this->belongsTo(SourceImportRun::class, 'source_import_run_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
