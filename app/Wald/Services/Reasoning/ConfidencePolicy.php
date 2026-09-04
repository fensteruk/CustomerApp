<?php

namespace App\Wald\Services\Reasoning;

use App\Wald\Contracts\Reasoning\RiskClass;

final class ConfidencePolicy
{
    public const VERSION = 'wald.confidence.v1';

    public const FAMILY_CAPS = ['heading' => 30, 'value_distribution' => 25, 'surrounding_structure' => 15, 'confirmed_profile' => 20, 'format_metadata' => 10];

    public const PLAUSIBLE = 35;

    public const REVIEWABLE = 50;

    public function thresholds(RiskClass $risk): array
    {
        return match ($risk) {
            RiskClass::Critical => ['strength' => 95, 'margin' => 20, 'families' => 2, 'full_validation' => true],
            RiskClass::Material => ['strength' => 85, 'margin' => 15, 'families' => 2, 'full_validation' => false],
            RiskClass::Descriptive => ['strength' => 70, 'margin' => 10, 'families' => 2, 'full_validation' => false],
        };
    }

    public function snapshot(): array
    {
        return ['version' => self::VERSION, 'family_caps' => self::FAMILY_CAPS, 'plausible' => self::PLAUSIBLE, 'reviewable' => self::REVIEWABLE,
            'thresholds' => array_combine(array_column(RiskClass::cases(), 'value'), array_map(fn ($risk) => $this->thresholds($risk), RiskClass::cases())),
            'bands' => ['high_confidence', 'needs_confirmation', 'unrecognised']];
    }
}
