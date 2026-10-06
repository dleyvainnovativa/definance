<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates arqueo settings: the difference account (must be postable, since the
 * adjusting entry posts to it) and an optional subset of counted accounts.
 */
class SaveCashCountSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'difference_account_id' => ['required', new OwnedAccount(requirePostable: true)],
            'counted_account_ids' => ['nullable', 'array'],
            'counted_account_ids.*' => [new OwnedAccount(requirePostable: false)],
        ];
    }
}
