<?php

namespace App\Enums;

enum CallOffServiceType: string
{
    case CavityClosers = 'cavity_closers';
    case Windows = 'windows';
    case Snagging = 'snagging';
    case Cml = 'cml';

    public function label(): string
    {
        return match ($this) {
            self::CavityClosers => 'Cavity Closers',
            self::Windows => 'Windows',
            self::Snagging => 'Snagging',
            self::Cml => 'CML',
        };
    }
}
