<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a saved annual budget: a year and one amount per owned account.
 * Amounts are non-negative budget figures (not postings).
 */
class SaveBudgetRequest extends FormRequest
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
            'rows.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
