<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates saved monthly budgets: a year and (account, month 1–12, amount) rows.
 */
class SaveBudgetMonthlyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.account_id' => ['required', new OwnedAccount(requirePostable: false)],
            'rows.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            'rows.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
