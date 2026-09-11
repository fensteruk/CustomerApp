<?php

namespace App\Models;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
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
        'requested_date',
        'reason_code',
        'reason_label',
        'requested_by_user_id',
        'requester_name',
        'requester_role',
        'is_urgent',
        'is_early_date_exception',
        'normal_earliest_date',
        'resulting_agreed_date',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => CallOffNegotiationPurpose::class,
            'status' => CallOffNegotiationStatus::class,
            'prior_agreed_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'requested_date' => 'date',
            'is_urgent' => 'boolean',
            'is_early_date_exception' => 'boolean',
            'normal_earliest_date' => 'date',
            'resulting_agreed_date' => 'date',
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

    public function isAmendment(): bool
    {
        return $this->purpose === CallOffNegotiationPurpose::Amendment;
    }

    public function progressLabel(): string
    {
        if ($this->status->isOpen()) {
            $this->loadMissing('proposals');

            return $this->proposals->contains(fn ($proposal) => $proposal->proposal_type === CallOffDateProposalType::FensterAlternativeDate
                && $proposal->status === CallOffDateProposalStatus::AwaitingResponse)
                ? 'Awaiting Site User' : 'Awaiting Fenster';
        }

        return match ($this->status) {
            CallOffNegotiationStatus::DateAgreed => 'Date Agreed',
            CallOffNegotiationStatus::Completed => 'Closed by source completion',
            CallOffNegotiationStatus::Withdrawn => 'Withdrawn',
            CallOffNegotiationStatus::Superseded => 'Superseded',
        };
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
