<?php

namespace App\Wald\Contracts\Reasoning;

interface EvidenceProvenanceVerifier
{
    public function accepts(Evidence $evidence): bool;
}
