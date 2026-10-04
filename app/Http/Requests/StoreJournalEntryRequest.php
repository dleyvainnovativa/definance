<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use App\Support\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],

            'legs' => ['required', 'array', 'min:2'],
            'legs.*.account_id' => ['required', new OwnedAccount],
            'legs.*.debit' => ['nullable', 'numeric', 'min:0'],
            'legs.*.credit' => ['nullable', 'numeric', 'min:0'],
            'legs.*.description' => ['nullable', 'string', 'max:255'],
            'legs.*.tax_code' => ['nullable', 'string', 'max:50'],
            'legs.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'legs.*.tax_base' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $legs = $this->input('legs', []);
            if (! is_array($legs)) {
                return;
            }

            $debit = '0';
            $credit = '0';

            foreach ($legs as $i => $leg) {
                $d = Money::of($leg['debit'] ?? null);
                $c = Money::of($leg['credit'] ?? null);

                // Exactly one side, strictly positive.
                if (Money::isPositive($d) === Money::isPositive($c)) {
                    $validator->errors()->add("legs.$i", 'Each leg must have exactly one positive side: debit or credit.');
                }

                $debit = Money::add($debit, $d);
                $credit = Money::add($credit, $c);
            }

            if (Money::cmp($debit, $credit) !== 0) {
                $validator->errors()->add('legs', "Entry is unbalanced: debits {$debit} ≠ credits {$credit}.");
            }
        });
    }
}
