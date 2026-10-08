<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates cierre settings: the result account that receives the yearly result.
 * Must be postable (the closing entry posts to it); normally an equity account.
 */
class SaveClosingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'result_account_id' => ['required', new OwnedAccount(requirePostable: true)],
        ];
    }
}
