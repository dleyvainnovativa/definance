<?php

namespace App\Http\Resources;

use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntryLine
 */
class JournalEntryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_code' => $this->whenLoaded('account', fn () => $this->account->code),
            'account_name' => $this->whenLoaded('account', fn () => $this->account->name),
            'debit' => $this->debit,
            'credit' => $this->credit,
            'description' => $this->line_description,
            'tax_code' => $this->tax_code,
        ];
    }
}
