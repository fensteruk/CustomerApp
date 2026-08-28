<?php

namespace App\Services;

use App\Enums\CallOffServiceType;

class SourceCallTypeMapper
{
    public function serviceFor(string $callType): ?CallOffServiceType
    {
        return match (mb_strtoupper(trim($callType))) {
            'PC1' => CallOffServiceType::Windows,
            'CC!', 'CC1' => CallOffServiceType::CavityClosers,
            'CM1' => CallOffServiceType::Snagging,
            'CM2', 'CML' => CallOffServiceType::Cml,
            default => null,
        };
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
