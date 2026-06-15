<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_historial' => $this->id,
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
            'fecha' => $this->fecha?->toIso8601String(),
            'estado' => $this->estado,
            'ip' => $this->ip,
            'usuario' => $this->usuario,
            'nombre_usuario' => $this->nombre_usuario ?: $this->user?->funcionario,
            'id' => $this->id,
            'input_id' => $this->id_insumo,
            'description' => $this->descripcion,
            'price' => $this->precio !== null ? (float) $this->precio : null,
            'type_id' => $this->tipo,
            'category_id' => $this->id_categoria,
            'category_name' => $this->category?->descripcion,
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
