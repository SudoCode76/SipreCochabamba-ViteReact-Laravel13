<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_log,
            'input_id' => $this->id_insumo,
            'description' => $this->descripcion,
            'price' => $this->precio !== null ? (float) $this->precio : null,
            'type_id' => $this->tipo,
            'unit_measure_id' => $this->unidad_medida,
            'action' => $this->accion,
            'status' => $this->estado,
            'date' => $this->fecha?->toDateString(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user ? [
                'id' => $this->user->id_usuario,
                'full_name' => $this->user->funcionario,
                'username' => $this->user->username,
                'status' => $this->user->estado,
            ] : null),
        ];
    }
}
