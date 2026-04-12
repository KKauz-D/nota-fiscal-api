<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'action' => $this->action,
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'result' => $this->result,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
