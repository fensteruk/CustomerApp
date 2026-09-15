<?php

namespace App\SourceImport\Integration;

final class IdenticalPilotImportConflict extends ImportConflict
{
    public function __construct(public readonly string $existingUploadUuid)
    {
        parent::__construct('identical_pilot_import_exists');
    }
}
