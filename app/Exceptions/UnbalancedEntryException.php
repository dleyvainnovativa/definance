<?php

namespace App\Exceptions;

class UnbalancedEntryException extends PostingException
{
    public static function make(string $debit, string $credit): self
    {
        return new self("Entry is unbalanced: debits {$debit} ≠ credits {$credit}.");
    }
}
