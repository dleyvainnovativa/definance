<?php

namespace App\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Posted => 'Contabilizado',
            self::Void => 'Cancelado',
        };
    }
}
