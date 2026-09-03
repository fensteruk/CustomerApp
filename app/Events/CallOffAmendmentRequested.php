<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

readonly class CallOffAmendmentRequested implements ShouldDispatchAfterCommit
{
    public function __construct(public int $callOffRequestId, public string $negotiationUuid) {}
}
