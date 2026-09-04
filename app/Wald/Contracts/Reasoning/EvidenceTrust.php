<?php

namespace App\Wald\Contracts\Reasoning;

enum EvidenceTrust: string
{
    case Structural = 'structural';
    case Constraint = 'code_owned_constraint';
    case Dictionary = 'confirmed_dictionary';
    case Profile = 'confirmed_profile';
    case Similarity = 'inferred_similarity';
}
