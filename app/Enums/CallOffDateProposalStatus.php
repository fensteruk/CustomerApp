<?php

namespace App\Enums;

enum CallOffDateProposalStatus: string
{
    case AwaitingResponse = 'awaiting_response';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';
}
