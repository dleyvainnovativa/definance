<?php

namespace App\Http\Resources;

use App\Models\LoginDevice;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoginDevice
 */
class LoginDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => UserAgent::describe($this->user_agent),
            'ip_address' => $this->ip_address,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
