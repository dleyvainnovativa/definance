<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a saved arqueo: a date and the counted amount per cash account.
 * Book balances are recomputed server-side, never trusted from the client.
 */
class SaveCashCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.account_id' => ['required', new OwnedAccount(requirePostable: false)],
            'counts.*.counted' => ['required', 'numeric', 'min:0'],
            // Optional MXN denomination breakdown (bills/coins). When present, the
            // server recomputes `counted` from it (value × qty) as the source of truth.
            'counts.*.denominations' => ['nullable', 'array'],
            'counts.*.denominations.*.value' => ['required', 'numeric', 'min:0'],
            'counts.*.denominations.*.qty' => ['required', 'integer', 'min:0'],
        ];
    }
}
