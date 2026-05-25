<?php

namespace App\Http\Resources\InputRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InputRequestQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $official = $this->filePayload($this->archivo, 'Propuesta oficial');
        $proposalOne = $this->filePayload($this->archivo1, 'Propuesta alternativa 1');
        $proposalTwo = $this->filePayload($this->archivo2, 'Propuesta alternativa 2');

        return [
            'id_cotizacion' => $this->id_cotizacion,
            'id_solicitud' => $this->id_solicitud,
            'fecha' => $this->fecha?->toDateString(),
            'archivo' => $this->archivo,
            'archivo_url' => $official['url'],
            'archivo_available' => $official['available'],
            'archivo_label' => $official['label'],
            'archivo1' => $this->archivo1,
            'archivo1_url' => $proposalOne['url'],
            'archivo1_available' => $proposalOne['available'],
            'archivo1_label' => $proposalOne['label'],
            'archivo2' => $this->archivo2,
            'archivo2_url' => $proposalTwo['url'],
            'archivo2_available' => $proposalTwo['available'],
            'archivo2_label' => $proposalTwo['label'],
            'estado' => $this->estado,
            'condicion' => $this->condicion,
            'id_log_insumo' => $this->id_log_insumo,
        ];
    }

    private function filePayload(?string $path, string $label): array
    {
        $cleanPath = is_string($path) ? ltrim(trim($path), '/') : null;
        $available = $cleanPath !== null
            && $cleanPath !== ''
            && Storage::disk('public')->exists($cleanPath);

        return [
            'available' => $available,
            'url' => $available ? Storage::disk('public')->url($cleanPath) : null,
            'label' => $cleanPath ? $label : null,
        ];
    }
}
