<?php

namespace App\Services;

use App\Enums\CallOffServiceType;

class SourceCallTypeMapper
{
    public function __construct(private readonly SiteAppImportDataDictionary $dictionary) {}

    public function serviceFor(string $callType): ?CallOffServiceType
    {
        return $this->dictionary->portalServiceFor($callType);
    }

    public function isCompletionStage(CallOffServiceType $service, ?string $jobStage): bool
    {
        return in_array(mb_strtoupper(trim((string) $jobStage)), match ($service) {
            CallOffServiceType::CavityClosers => ['CC08'],
            CallOffServiceType::Windows => ['CA02', 'CA03'],
            CallOffServiceType::Snagging => ['SN05'],
            CallOffServiceType::Cml => ['CML4'],
        }, true);
    }
}
