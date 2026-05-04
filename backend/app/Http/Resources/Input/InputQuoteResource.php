<?php

namespace App\Http\Resources\Input;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_cotizacion' => $this->id_cotizacion,
            'id_insumo' => $this->id_insumo,
            'condicion' => $this->condicion,
            'estado' => $this->estado,
            'id_log_insumo' => $this->id_log_insumo,
            'archivo' => $this->archivo,
            'fecha' => $this->fecha?->toDateString(),
            'archivo1' => $this->archivo1,
            'archivo2' => $this->archivo2,
            'id_solicitud' => $this->id_solicitud,
            'id' => $this->id_cotizacion,
            'input_id' => $this->id_insumo,
            'condition' => $this->condicion,
            'status' => $this->estado,
            'log_id' => $this->id_log_insumo,
            'file' => $this->archivo,
            'date' => $this->fecha?->toDateString(),
            'file_1' => $this->archivo1,
            'file_2' => $this->archivo2,
            'request_id' => $this->id_solicitud,
            'input_description' => $this->input?->descripcion,
        ];
    }
}
