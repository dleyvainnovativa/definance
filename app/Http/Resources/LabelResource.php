<?php

namespace App\Http\Resources;

use App\Models\Etiqueta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Etiqueta */
class LabelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'accounts_count' => $this->whenCounted('accounts'),
            'account_ids' => $this->whenLoaded('accounts', fn () => $this->accounts->pluck('id')),
        ];
    }
}
