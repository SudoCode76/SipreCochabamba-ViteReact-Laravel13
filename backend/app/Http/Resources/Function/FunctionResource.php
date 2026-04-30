<?php

namespace App\Http\Resources\Function;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FunctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_funcion,
            'name' => $this->nombre_funcion,
            'description' => $this->descripcion,
            'class' => $this->clase,
            'status' => $this->estado,
        ];
    }
}
