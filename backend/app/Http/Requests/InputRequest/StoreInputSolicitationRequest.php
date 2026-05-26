<?php

namespace App\Http\Requests\InputRequest;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputSolicitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $legacy = $this->input('solicitud', []);

        $this->merge([
            'description' => $this->input('description', $this->input('descripcion', data_get($legacy, 'descripcion'))),
            'price' => $this->input('price', $this->input('precio', data_get($legacy, 'precio'))),
            'unit_measure_id' => $this->input('unit_measure_id', $this->input('unidad_medida', data_get($legacy, 'unidad_medida'))),
            'type_id' => $this->input('type_id', $this->input('tipo', data_get($legacy, 'tipo'))),
            'location' => $this->input('location', $this->input('ubicacion', data_get($legacy, 'ubicacion'))),
            'justification' => $this->input('justification', $this->input('justificacion', data_get($legacy, 'justificacion'))),
            'requester_id' => $this->input('requester_id', $this->input('usuario_solicitante', data_get($legacy, 'usuario_solicitante'))),
            'approval_status' => $this->input('approval_status', $this->input('estado_aprobacion', data_get($legacy, 'estado_aprobacion'))),
            'notification' => $this->input('notification', $this->input('notificacion', data_get($legacy, 'notificacion'))),
            'date' => $this->input('date', $this->input('fecha', data_get($legacy, 'fecha'))),
            'latitude' => $this->input('latitude', $this->input('latitud', data_get($legacy, 'latitud'))),
            'longitude' => $this->input('longitude', $this->input('longitud', data_get($legacy, 'longitud'))),
            'district' => $this->input('district', $this->input('distrito', data_get($legacy, 'distrito'))),
            'zone' => $this->input('zone', $this->input('zona', data_get($legacy, 'zona'))),
            'otb' => $this->input('otb', data_get($legacy, 'otb')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit_measure_id' => ['required', 'integer', 'exists:unidad_medida,id_unidad_medida'],
            'type_id' => ['required', 'integer', 'exists:tipo_insumo,id_tipo'],
            'location' => ['required', 'string', 'max:255'],
            'justification' => ['required', 'string', 'max:500'],
            'requester_id' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'approval_status' => ['nullable', 'string', 'size:2', 'in:PD,AP,RC'],
            'notification' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'latitude' => ['nullable', 'string', 'max:80'],
            'longitude' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:100'],
            'zone' => ['nullable', 'string', 'max:150'],
            'otb' => ['nullable', 'string', 'max:150'],
            'valido' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'propuesto_1' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'propuesto_2' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
