<?php

namespace App\Http\Requests\InputRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInputSolicitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description', $this->input('descripcion')),
            'price' => $this->input('price', $this->input('precio')),
            'unit_measure_id' => $this->input('unit_measure_id', $this->input('unidad_medida')),
            'type_id' => $this->input('type_id', $this->input('tipo')),
            'location' => $this->input('location', $this->input('ubicacion')),
            'justification' => $this->input('justification', $this->input('justificacion')),
            'requester_id' => $this->input('requester_id', $this->input('usuario_solicitante')),
            'approval_status' => $this->input('approval_status', $this->input('estado_aprobacion')),
            'notification' => $this->input('notification', $this->input('notificacion')),
            'date' => $this->input('date', $this->input('fecha')),
            'adj' => $this->input('adj'),
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
            'approval_status' => ['required', 'string', 'size:2', 'in:PD,AP,RC'],
            'notification' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'adj' => ['nullable', 'string', 'in:SI,NO'],
            'valido' => ['nullable', 'file', 'max:10240'],
            'propuesto_1' => ['nullable', 'file', 'max:10240'],
            'propuesto_2' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
