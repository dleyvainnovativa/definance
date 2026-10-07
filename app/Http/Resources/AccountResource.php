<?php

namespace App\Http\Resources;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChartOfAccount
 */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),            // Activo, Pasivo, …
            'normal_balance' => $this->normal_balance->value, // debit / credit
            'nature_label' => $this->normal_balance->label(), // Deudora / Acreedora
            'is_postable' => $this->is_postable,
            'is_active' => $this->is_active,
            'is_cash' => $this->is_cash,
            'is_editable' => $this->is_editable,
            'is_deletable' => $this->is_deletable,
            'labels' => $this->whenLoaded('etiquetas', fn () => $this->etiquetas->map(fn ($e) => [
                'id' => $e->id, 'name' => $e->name, 'color' => $e->color,
            ])->values()),
            'children' => AccountResource::collection($this->whenLoaded('children')),
        ];
    }
}
