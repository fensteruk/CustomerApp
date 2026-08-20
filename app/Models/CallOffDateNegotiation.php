<?php

namespace App\Models;

use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallOffDateNegotiation extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'call_off_request_id',
        'purpose',
        'status',
        'active_negotiation_key',
        'prior_agreed_date',
        'customer_response',
        'internal_reason',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => CallOffNegotiationPurpose::class,
            'status' => CallOffNegotiationStatus::class,
            'prior_agreed_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CallOffRequest, $this> */
    public function callOffRequest(): BelongsTo
    {
        return $this->belongsTo(CallOffRequest::class);
    }

    /** @return HasMany<CallOffDateProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(CallOffDateProposal::class);
    }
}
