<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceProjectionEvent extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['source_import_run_id', 'projected_plot_service_id', 'call_off_request_id', 'event_type', 'before_state', 'after_state', 'occurred_at'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'occurred_at' => 'datetime'];
    }
}
