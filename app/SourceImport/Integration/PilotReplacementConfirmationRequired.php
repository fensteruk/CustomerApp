<?php

namespace App\SourceImport\Integration;

final class PilotReplacementConfirmationRequired extends ImportConflict
{
    public function __construct(public readonly string $existingUploadUuid)
    {
        parent::__construct('pilot_replacement_confirmation_required');
    }
}
