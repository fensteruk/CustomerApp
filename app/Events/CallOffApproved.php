<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CallOffApproved implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $callOffRequestId) {}
}
