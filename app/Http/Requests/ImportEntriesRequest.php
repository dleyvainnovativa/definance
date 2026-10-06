<?php

namespace App\Http\Requests;

use App\Services\Import\ImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape validation for a bulk import. Accounting validation (owned/postable
 * account, debit XOR credit, Σdebits = Σcredits, date) is done per group in
 * ImportService so each row/group gets a friendly, line-numbered error.
 */
class ImportEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge(
            ['mode' => ['required', Rule::in([ImportService::MODE_ALL_OR_NOTHING, ImportService::MODE_SKIP_INVALID])]],
            self::rowRules(),
        );
    }

    /**
     * Row-shape rules, shared with the preview endpoint (which needs no mode).
     *
     * @return array<string,mixed>
     */
    public static function rowRules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:5000'],
            'rows.*.group' => ['nullable'],
            'rows.*.entry_date' => ['nullable', 'string', 'max:40'],
            'rows.*.description' => ['nullable', 'string', 'max:1000'],
            'rows.*.reference' => ['nullable', 'string', 'max:255'],
            'rows.*.account_code' => ['nullable', 'string', 'max:50'],
            'rows.*.line_description' => ['nullable', 'string', 'max:255'],
            'rows.*.debit' => ['nullable', 'numeric', 'min:0'],
            'rows.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
