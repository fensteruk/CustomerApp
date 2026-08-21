<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CallOffAlternativeProposed implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $callOffRequestId, public readonly string $proposalUuid) {}
}
