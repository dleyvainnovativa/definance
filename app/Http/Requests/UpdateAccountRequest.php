<?php

namespace App\Http\Requests;

use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $accountId = $this->route('account')?->id;

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('chart_of_accounts', 'code')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id))
                    ->ignore($accountId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['nullable', new OwnedAccount(requirePostable: false)],
            'is_postable' => ['boolean'],
            'is_active' => ['boolean'],
            // type is immutable once an account exists (it changes nature/meaning).
        ];
    }
}
