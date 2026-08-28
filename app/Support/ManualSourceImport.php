<?php

namespace App\Support;

final class ManualSourceImport
{
    public const SOURCE_NAMESPACE = 'siteapp-xlsx';

    public const PREVIEW_STATUS_READY = 'ready';

    public const PREVIEW_STATUS_MAPPING_REQUIRED = 'mapping_required';

    public const PREVIEW_STATUS_COMMITTED = 'committed';

    public const PREVIEW_STATUS_FAILED = 'failed';

    public const PREVIEW_STATUS_EXPIRED = 'expired';

    private function __construct() {}
}
