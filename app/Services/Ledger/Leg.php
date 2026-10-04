<?php

namespace App\Services\Ledger;

use App\Exceptions\PostingException;
use App\Support\Money;

/**
 * One leg of a journal entry: a single account posted to on exactly one side.
 * Immutable; amounts are kept as ledger-scale strings.
 */
final readonly class Leg
{
    public string $debit;

    public string $credit;

    public ?string $taxRate;

    public ?string $taxBase;

    public function __construct(
        public int $accountId,
        int|float|string|null $debit = null,
        int|float|string|null $credit = null,
        public ?string $description = null,
        public ?string $taxCode = null,
        int|float|string|null $taxRate = null,
        int|float|string|null $taxBase = null,
    ) {
        $this->debit = Money::of($debit);
        $this->credit = Money::of($credit);
        $this->taxRate = $taxRate === null ? null : (string) $taxRate;
        $this->taxBase = $taxBase === null ? null : (string) $taxBase;

        // Exactly one side, strictly positive, the other zero.
        if (Money::isPositive($this->debit) === Money::isPositive($this->credit)) {
            throw PostingException::invalidLegSide();
        }
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accountId: (int) $data['account_id'],
            debit: $data['debit'] ?? null,
            credit: $data['credit'] ?? null,
            description: $data['description'] ?? $data['line_description'] ?? null,
            taxCode: $data['tax_code'] ?? null,
            taxRate: $data['tax_rate'] ?? null,
            taxBase: $data['tax_base'] ?? null,
        );
    }

    /** @return array<string,mixed> */
    public function toLineAttributes(int $userId): array
    {
        return [
            'user_id' => $userId,
            'account_id' => $this->accountId,
            'debit' => Money::isPositive($this->debit) ? $this->debit : null,
            'credit' => Money::isPositive($this->credit) ? $this->credit : null,
            'line_description' => $this->description,
            'tax_code' => $this->taxCode,
            'tax_rate' => $this->taxRate,
            'tax_base' => $this->taxBase,
        ];
    }
}
