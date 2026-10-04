<?php

namespace App\Http\Resources;

use App\Models\JournalEntry;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date?->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reversed_entry_id' => $this->reversed_entry_id,
            'totals' => $this->when(
                $this->relationLoaded('lines'),
                fn () => [
                    'debit' => Money::sum($this->lines->pluck('debit')),
                    'credit' => Money::sum($this->lines->pluck('credit')),
                ],
            ),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
