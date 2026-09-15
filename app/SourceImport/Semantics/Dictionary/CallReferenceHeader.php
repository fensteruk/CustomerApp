<?php

namespace App\SourceImport\Semantics\Dictionary;

final class CallReferenceHeader
{
    /**
     * Match only the approved semantic token pairs. Punctuation and spacing are
     * presentation differences; extra words and fuzzy spelling are never accepted.
     */
    public static function matches(string $header): bool
    {
        $compact = preg_replace('/[^a-z0-9]+/', '', strtolower($header));

        return in_array($compact, ['callno', 'nocall', 'callnumber', 'numbercall'], true);
    }
}
