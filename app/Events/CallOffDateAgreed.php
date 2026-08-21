<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CallOffDateAgreed implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $callOffRequestId) {}
}
