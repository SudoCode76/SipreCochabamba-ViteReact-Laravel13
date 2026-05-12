<?php

namespace App\Http\Requests\InputRequest;

use Illuminate\Foundation\Http\FormRequest;

class ManageInputSolicitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'approval_status' => $this->normalizeStatus($this->input('approval_status', $this->input('estado_aprobacion'))),
            'price' => $this->input('price', $this->input('precio')),
            'unit_measure_id' => $this->input('unit_measure_id', $this->input('unidad_medida')),
            'location' => $this->input('location', $this->input('ubicacion')),
            'justification' => $this->input('justification', $this->input('justificacion')),
            'notification' => $this->input('notification', $this->input('notificacion')),
            'approval_user_id' => $this->input('approval_user_id', $this->input('usuario_aprobacion')),
            'approval_date' => $this->input('approval_date', $this->input('fecha_aprobacion')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_status' => ['required', 'string', 'size:2', 'in:AP,RC'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit_measure_id' => ['required', 'integer', 'exists:unidad_medida,id_unidad_medida'],
            'location' => ['nullable', 'string', 'max:255'],
            'justification' => ['required', 'string', 'max:500'],
            'notification' => ['required', 'string', 'max:255'],
            'approval_user_id' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'approval_date' => ['required', 'date'],
        ];
    }

    private function normalizeStatus(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtoupper(trim($value))) {
            'APROBADO' => 'AP',
            'RECHAZADO' => 'RC',
            default => strtoupper(trim($value)),
        };
    }
}
