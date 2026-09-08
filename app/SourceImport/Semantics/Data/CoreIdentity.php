<?php

namespace App\SourceImport\Semantics\Data;

final class CoreIdentity
{
    public const SHA = '4aa5ffb5a00527662ddfe66673edbfb18af9f0db';

    public const READER = 'wald-0.2.1';

    public static function snapshot(): array
    {
        return ['commit' => self::SHA, 'reader' => self::READER, 'reasoning' => 'wald-0.3.0',
            'structure' => 'wald.structure.v1.1', 'rules' => 'wald.generic-rules.v1',
            'confidence' => 'wald.confidence.v1'];
    }
}
