<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_log' => $this->id_log,
            'id_insumo' => $this->id_insumo,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio !== null ? (float) $this->precio : null,
            'tipo' => $this->tipo,
            'id_categoria' => $this->id_categoria,
            'unidad_medida' => $this->unidad_medida,
            'nombre_tipo' => $this->type?->descripcion,
            'nombre_categoria' => $this->category?->descripcion,
            'nombre_unidad_medida' => $this->unitMeasure?->descripcion,
            'abreviatura' => $this->unitMeasure?->abreviatura,
            'accion' => $this->accion,
            'estado' => $this->estado,
            'fecha' => $this->fecha?->toDateString(),
            'id' => $this->id_log,
            'input_id' => $this->id_insumo,
            'description' => $this->descripcion,
            'price' => $this->precio !== null ? (float) $this->precio : null,
            'type_id' => $this->tipo,
            'category_id' => $this->id_categoria,
            'category_name' => $this->category?->descripcion,
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
