<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a saved FEA month: a period (YYYY-MM) and one planned net amount per
 * owned account. planned_amount may be negative (a net outflow). Accounts need
 * not be postable — this stores a plan, not a posting.
 */
class SaveManagedCashFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.account_id' => ['required', new OwnedAccount(requirePostable: false)],
            'rows.*.planned_amount' => ['required', 'numeric'],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
