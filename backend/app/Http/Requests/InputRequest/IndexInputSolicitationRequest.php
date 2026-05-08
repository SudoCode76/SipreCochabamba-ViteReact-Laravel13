<?php

namespace App\Http\Requests\InputRequest;

use Illuminate\Foundation\Http\FormRequest;

class IndexInputSolicitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'approval_status' => $this->input('approval_status', $this->input('estado_aprobacion')),
            'requester_id' => $this->input('requester_id', $this->input('usuario_solicitante')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'approval_status' => ['nullable', 'string', 'size:2', 'in:PD,AP,RC'],
            'type_id' => ['nullable', 'integer', 'exists:tipo_insumo,id_tipo'],
            'unit_measure_id' => ['nullable', 'integer', 'exists:unidad_medida,id_unidad_medida'],
            'requester_id' => ['nullable', 'integer', 'exists:usuario,id_usuario'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
