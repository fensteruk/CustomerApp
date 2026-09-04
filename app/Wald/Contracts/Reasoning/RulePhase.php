<?php

namespace App\Wald\Contracts\Reasoning;

enum RulePhase: int
{
    case Structure = 10;
    case Candidates = 20;
    case Evidence = 30;
    case Contradiction = 40;
    case Constraints = 50;
    case Confidence = 60;
}
