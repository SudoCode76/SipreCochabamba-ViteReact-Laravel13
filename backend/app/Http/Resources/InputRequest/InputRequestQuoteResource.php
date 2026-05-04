<?php

namespace App\Http\Resources\InputRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputRequestQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_cotizacion' => $this->id_cotizacion,
            'id_solicitud' => $this->id_solicitud,
            'fecha' => $this->fecha?->toDateString(),
            'archivo' => $this->archivo,
            'archivo1' => $this->archivo1,
            'archivo2' => $this->archivo2,
            'estado' => $this->estado,
            'condicion' => $this->condicion,
            'id_log_insumo' => $this->id_log_insumo,
        ];
    }
}
