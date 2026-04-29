<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'input_id' => $this->id_insumo,
            'description' => $this->descripcion,
            'price' => $this->precio !== null ? (float) $this->precio : null,
            'type_id' => $this->tipo,
            'unit_measure_id' => $this->unidad_medida,
            'action' => $this->accion,
            'status' => $this->estado,
            'ip' => $this->ip,
            'performed_at' => $this->fecha?->toIso8601String(),
            'user' => [
                'id' => $this->usuario,
                'full_name' => $this->nombre_usuario ?: $this->user?->funcionario,
                'username' => $this->user?->username,
            ],
        ];
    }
}
