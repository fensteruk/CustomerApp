<?php

namespace App\Data;

use App\Models\Site;
use App\Models\SourceSiteBinding;

readonly class SourceSiteResolution
{
    public function __construct(
        public Site $site,
        public ?SourceSiteBinding $binding,
    ) {}
}
