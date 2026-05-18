<?php

namespace App\Http\Resources\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_auditoria,
            'user_name' => $this->nombre_completo,
            'occurred_at' => $this->fecha_hora?->toIso8601String(),
            'ip' => $this->ip,
            'process' => $this->proceso,
        ];
    }
}
