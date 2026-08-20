<?php

namespace App\Enums;

enum CallOffDateProposalType: string
{
    case CustomerRequestedDate = 'customer_requested_date';
    case FensterAlternativeDate = 'fenster_alternative_date';
    case LegacyMappedDate = 'legacy_mapped_date';
}
