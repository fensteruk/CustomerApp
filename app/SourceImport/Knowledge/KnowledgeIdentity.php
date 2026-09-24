<?php

namespace App\SourceImport\Knowledge;

use App\SourceImport\Integration\MasterSourceResolver;
use App\SourceImport\Semantics\Data\CoreIdentity;
use App\SourceImport\Semantics\Dictionary\CustomerAppDictionary;
use LogicException;

final class KnowledgeIdentity
{
    public const BASELINE = 'a80ce7d14206cf3f3a9343448d406f01ae927b88';

    public const FINGERPRINT = '8f2cec47b59b61a35f50e8c5e96e1aeedc0ac5dfe80ecb10922b51bfa646281b';

    public const POLICY = 'customerapp.wald-knowledge-policy.v2';

    public function current(): array
    {
        $dictionary = (new CustomerAppDictionary)->identity();
        if ($dictionary->fingerprint !== self::FINGERPRINT) {
            throw new LogicException('dictionary_integrity_error');
        }

        return ['dictionary' => CustomerAppDictionary::VERSION, 'fingerprint' => self::FINGERPRINT,
            'core' => CoreIdentity::snapshot(), 'reader_adapters' => ['xlsx' => 3, 'csv' => 2],
            'schema' => 'customerapp.wald-knowledge.v1', 'signature' => 'customerapp.wald-profile-signature.v1',
            'matcher' => 'customerapp.wald-profile-compatibility.v1', 'selector' => 'customerapp.wald-selector.v1',
            'semantic_executable' => 'f4fda0f069bd5106a125b42615ca212294a9dfad',
            'accepted_baseline' => self::BASELINE, 'policy' => self::POLICY,
            'resolution' => MasterSourceResolver::VERSION];
    }

    public function compatible(array $pins): Compatibility
    {
        $current = $this->current();
        if (($pins['dictionary'] ?? null) === $current['dictionary'] && ($pins['fingerprint'] ?? null) !== $current['fingerprint']) {
            return Compatibility::Incompatible;
        }

        return Canonical::hash($pins) === Canonical::hash($current) ? Compatibility::Exact : Compatibility::Stale;
    }
}
