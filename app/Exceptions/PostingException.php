<?php

namespace App\Exceptions;

use RuntimeException;

/** Base error for any invalid attempt to post to the ledger. */
class PostingException extends RuntimeException
{
    public static function tooFewLegs(): self
    {
        return new self('A journal entry needs at least two legs.');
    }

    public static function invalidLegSide(): self
    {
        return new self('Each leg must have exactly one positive side: debit XOR credit.');
    }

    public static function accountNotPostable(string $code): self
    {
        return new self("Account {$code} is not postable (group or inactive account).");
    }

    public static function accountNotOwned(int $accountId): self
    {
        return new self("Account #{$accountId} does not exist or does not belong to this user.");
    }

    public static function notPosted(): self
    {
        return new self('Only a posted entry can be voided.');
    }

    public static function notDraft(): self
    {
        return new self('Only a draft entry can be edited or posted.');
    }

    public static function notPostedForMeta(): self
    {
        return new self('Only a posted entry can have its date, description or reference edited.');
    }
}
