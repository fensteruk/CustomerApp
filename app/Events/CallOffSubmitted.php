<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CallOffSubmitted implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $callOffRequestId) {}
}
