<?php

namespace App\Wald\Services\Reasoning;

final class GenericRuleSettings
{
    public const VERSION = 'wald.generic-shapes.v1';

    public const WEIGHTS = ['header' => 30, 'datatype' => 15, 'specialised_datatype' => 25, 'uniqueness' => 15, 'cardinality' => 10, 'null_density' => 5, 'pattern' => 10, 'position' => 15, 'repeated' => 5, 'contradiction' => 20, 'hidden' => 10, 'formula' => 10];

    public const RATIOS = ['compatible' => 0.8, 'unique' => 0.98, 'low_cardinality' => 0.3, 'incompatible' => 0.2, 'mostly_blank' => 0.5, 'table_density' => 0.5];

    public const MINIMUM_OBSERVATIONS = 3;

    public const FREE_TEXT_LENGTH = 40;

    public static function snapshot(): array
    {
        return ['version' => self::VERSION, 'weights' => self::WEIGHTS, 'ratios' => self::RATIOS, 'minimum_observations' => self::MINIMUM_OBSERVATIONS, 'free_text_length' => self::FREE_TEXT_LENGTH];
    }
}
