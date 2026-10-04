<?php

namespace App\Enums;

enum NormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    /** Spanish accounting label (naturaleza). */
    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Deudora',
            self::Credit => 'Acreedora',
        };
    }
}
