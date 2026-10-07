<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('label')?->id;

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('etiquetas', 'name')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id))
                    ->ignore($id),
            ],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
