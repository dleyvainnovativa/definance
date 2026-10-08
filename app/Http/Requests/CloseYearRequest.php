<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validates a cierre request: the fiscal year to close. */
class CloseYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
