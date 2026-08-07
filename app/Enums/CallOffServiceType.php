<?php

namespace App\Enums;

enum CallOffServiceType: string
{
    case CavityClosers = 'cavity_closers';
    case Windows = 'windows';
    case Cml = 'cml';

    public function label(): string
    {
        return match ($this) {
            self::CavityClosers => 'Cavity Closers',
            self::Windows => 'Windows',
            self::Cml => 'CML',
        };
    }
}
