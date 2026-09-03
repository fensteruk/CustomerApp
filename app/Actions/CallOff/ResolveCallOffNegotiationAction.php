<?php

namespace App\Actions\CallOff;

use App\Enums\CallOffDateProposalStatus;
use App\Enums\CallOffDateProposalType;
use App\Enums\CallOffNegotiationPurpose;
use App\Enums\CallOffNegotiationStatus;
use App\Enums\CallOffRequestStatus;
use App\Models\CallOffDateNegotiation;
use App\Models\CallOffDateProposal;
use App\Models\CallOffRequest;
use Illuminate\Validation\ValidationException;

/** Shared initial/amendment cycle selection, after the service/request locks. */
class ResolveCallOffNegotiationAction
{
    public function forOffice(CallOffRequest $request, ?string $expectedUuid): CallOffDateNegotiation
    {
        $isAmendment = $request->status === CallOffRequestStatus::AmendmentOnHold;
        $purpose = $isAmendment ? CallOffNegotiationPurpose::Amendment : CallOffNegotiationPurpose::Initial;
        $negotiation = $request->dateNegotiations()->where('purpose', $purpose)->where('status', CallOffNegotiationStatus::Open)->lockForUpdate()->first();
        if ($isAmendment && ($negotiation === null || $expectedUuid !== $negotiation->uuid)) {
            throw ValidationException::withMessages(['request' => 'This date change has changed. Review the current request before deciding.']);
        }
        if (! $isAmendment && $expectedUuid !== null) {
            throw ValidationException::withMessages(['request' => 'This date change is no longer available.']);
        }
        $negotiation ??= $request->dateNegotiations()->create([
            'purpose' => $purpose, 'status' => CallOffNegotiationStatus::Open,
            'active_negotiation_key' => 'initial:'.$request->id, 'opened_at' => now(),
        ]);
        if ($negotiation->proposals()->where('proposal_type', CallOffDateProposalType::FensterAlternativeDate)->where('status', CallOffDateProposalStatus::AwaitingResponse)->exists()) {
            throw ValidationException::withMessages(['proposal' => 'A current alternative is awaiting the site user.']);
        }

        return $negotiation;
    }

    /** @return array{CallOffDateNegotiation, CallOffDateProposal} */
    public function forResponse(CallOffRequest $request, CallOffDateProposal $reference): array
    {
        $negotiation = $request->dateNegotiations()
            ->whereHas('proposals', fn ($query) => $query->whereKey($reference->id))
            ->lockForUpdate()->first();
        if ($negotiation === null) {
            throw ValidationException::withMessages(['proposal' => 'This alternative is no longer available.']);
        }
        $proposal = $negotiation->proposals()->whereKey($reference->id)->lockForUpdate()->firstOrFail();
        $purpose = $request->status === CallOffRequestStatus::AmendmentOnHold ? CallOffNegotiationPurpose::Amendment : CallOffNegotiationPurpose::Initial;
        if ((int) $negotiation->call_off_request_id !== (int) $request->id
            || $negotiation->purpose !== $purpose
            || ! $negotiation->status->isOpen()
            || $negotiation->active_negotiation_key === null
            || $proposal->proposal_type !== CallOffDateProposalType::FensterAlternativeDate
            || $proposal->status !== CallOffDateProposalStatus::AwaitingResponse) {
            throw ValidationException::withMessages(['proposal' => 'This alternative is no longer available.']);
        }

        return [$negotiation, $proposal];
    }

    public function requestedProposal(CallOffDateNegotiation $negotiation, CallOffRequest $request): CallOffDateProposal
    {
        return $negotiation->proposals()->where('proposal_type', CallOffDateProposalType::CustomerRequestedDate)->lockForUpdate()->first()
            ?? $negotiation->proposals()->create([
                'sequence' => 1,
                'proposal_type' => CallOffDateProposalType::CustomerRequestedDate,
                'status' => CallOffDateProposalStatus::AwaitingResponse,
                'proposed_date' => $request->requested_date,
                'proposed_by_user_id' => $request->batch->submitted_by_user_id,
                'customer_response' => $request->customer_response,
                'is_earlier_date_exception' => $request->is_early_date_exception,
                'proposed_at' => now(),
            ]);
    }
}
