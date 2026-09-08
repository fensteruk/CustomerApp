<?php

namespace App\SourceImport\Knowledge;

use InvalidArgumentException;

/** Selected scope is not an authority token: every operation verifies it against the database. */
final readonly class KnowledgeScope
{
    public function __construct(public int $organisationId, public int $siteId, public string $namespace, public string $family)
    {
        if ($organisationId < 1 || $siteId < 1) {
            throw new InvalidArgumentException('knowledge_scope_required');
        }
        foreach ([$namespace, $family] as $key) {
            if (! preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/D', $key)) {
                throw new InvalidArgumentException('knowledge_scope_key_invalid');
            }
        }
    }

    public function columns(): array
    {
        return ['customer_organisation_id' => $this->organisationId, 'site_id' => $this->siteId,
            'source_namespace' => $this->namespace, 'workbook_family' => $this->family];
    }
}
