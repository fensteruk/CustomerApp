<?php

namespace App\Models;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallOffDateProposal extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'call_off_date_negotiation_id',
        'sequence',
        'proposal_type',
        'status',
        'proposed_date',
        'proposed_by_user_id',
        'responded_by_user_id',
        'customer_response',
        'internal_reason',
        'is_earlier_date_exception',
        'earlier_date_acknowledged_at',
        'proposed_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'proposal_type' => CallOffDateProposalType::class,
            'status' => CallOffDateProposalStatus::class,
            'proposed_date' => 'date',
            'is_earlier_date_exception' => 'boolean',
            'earlier_date_acknowledged_at' => 'datetime',
            'proposed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CallOffDateNegotiation, $this> */
    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(CallOffDateNegotiation::class, 'call_off_date_negotiation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
