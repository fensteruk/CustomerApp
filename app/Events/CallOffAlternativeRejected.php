<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CallOffAlternativeRejected implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $callOffRequestId, public readonly string $proposalUuid) {}
}
