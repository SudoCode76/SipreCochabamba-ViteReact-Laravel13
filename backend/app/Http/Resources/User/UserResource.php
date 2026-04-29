<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_usuario,
            'full_name' => $this->funcionario,
            'ci' => $this->ci,
            'username' => $this->username,
            'status' => $this->estado,
            'item' => $this->item,
            'date' => $this->fecha?->toDateString(),
            'subalcaldia' => $this->subalcaldia,
            'role' => $this->whenLoaded('role', fn (): ?array => $this->role ? [
                'id' => $this->role->id_rol,
                'name' => $this->role->nombre_rol,
                'status' => $this->role->estado,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'id' => $this->unit->id_unidad,
                'description' => $this->unit->descripcion,
                'status' => $this->unit->estado,
            ] : null),
        ];
    }
}
