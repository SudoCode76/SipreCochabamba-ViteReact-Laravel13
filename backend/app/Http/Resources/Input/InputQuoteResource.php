<?php

namespace App\Http\Resources\Input;

use App\Services\Files\PublicFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $official = $this->filePayload($this->archivo, 'Propuesta oficial');
        $proposalOne = $this->filePayload($this->archivo1, 'Propuesta alternativa 1');
        $proposalTwo = $this->filePayload($this->archivo2, 'Propuesta alternativa 2');
        $proposalThree = $this->filePayload($this->archivo3, 'Propuesta alternativa 3');

        return [
            'id_cotizacion' => $this->id_cotizacion,
            'id_insumo' => $this->id_insumo,
            'condicion' => $this->condicion,
            'estado' => $this->estado,
            'id_log_insumo' => $this->id_log_insumo,
            'archivo' => $this->archivo,
            'archivo_url' => $official['url'],
            'archivo_available' => $official['available'],
            'archivo_label' => $official['label'],
            'fecha' => $this->fecha?->toDateString(),
            'archivo1' => $this->archivo1,
            'archivo1_url' => $proposalOne['url'],
            'archivo1_available' => $proposalOne['available'],
            'archivo1_label' => $proposalOne['label'],
            'archivo2' => $this->archivo2,
            'archivo2_url' => $proposalTwo['url'],
            'archivo2_available' => $proposalTwo['available'],
            'archivo2_label' => $proposalTwo['label'],
            'archivo3' => $this->archivo3,
            'archivo3_url' => $proposalThree['url'],
            'archivo3_available' => $proposalThree['available'],
            'archivo3_label' => $proposalThree['label'],
            'id_solicitud' => $this->id_solicitud,
            'id' => $this->id_cotizacion,
            'input_id' => $this->id_insumo,
            'condition' => $this->condicion,
            'status' => $this->estado,
            'log_id' => $this->id_log_insumo,
            'file' => $this->archivo,
            'file_url' => $official['url'],
            'file_available' => $official['available'],
            'file_label' => $official['label'],
            'date' => $this->fecha?->toDateString(),
            'file_1' => $this->archivo1,
            'file_1_url' => $proposalOne['url'],
            'file_1_available' => $proposalOne['available'],
            'file_1_label' => $proposalOne['label'],
            'file_2' => $this->archivo2,
            'file_2_url' => $proposalTwo['url'],
            'file_2_available' => $proposalTwo['available'],
            'file_2_label' => $proposalTwo['label'],
            'file_3' => $this->archivo3,
            'file_3_url' => $proposalThree['url'],
            'file_3_available' => $proposalThree['available'],
            'file_3_label' => $proposalThree['label'],
            'request_id' => $this->id_solicitud,
            'input_description' => $this->input?->descripcion,
        ];
    }

    private function filePayload(?string $path, string $baseLabel): array
    {
        $files = app(PublicFileService::class);
        $available = $files->exists($path);

        return [
            'available' => $available,
            'url' => $available ? $files->url($path) : null,
            'label' => $path
                ? $baseLabel.' '.($this->estado === 'AC' ? 'vigente' : 'anterior')
                : null,
        ];
    }
}
