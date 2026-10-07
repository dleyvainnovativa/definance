<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Rules\OwnedAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('chart_of_accounts', 'code')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', new OwnedAccount(requirePostable: false)],
            'is_postable' => ['boolean'],
            'is_active' => ['boolean'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => [Rule::exists('etiquetas', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
