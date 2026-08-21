<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CallOffAlternativeAccepted implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $callOffRequestId, public readonly string $proposalUuid) {}
}
