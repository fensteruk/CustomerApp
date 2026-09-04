<?php

namespace App\Wald\Contracts\Reasoning;

use App\Wald\Contracts\WorkbookProfile;
use App\Wald\Services\Reasoning\HypothesisCatalog;

interface RuleContextProvider
{
    /** @return iterable<RuleContext> */
    public function contexts(WorkbookProfile $profile, HypothesisCatalog $catalog): iterable;
}
