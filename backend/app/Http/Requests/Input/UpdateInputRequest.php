<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInputRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description', $this->input('descripcion')),
            'unit_measure_id' => $this->input('unit_measure_id', $this->input('unidad_medida')),
            'price' => $this->input('price', $this->input('precio')),
            'type_id' => $this->input('type_id', $this->input('tipo')),
            'status' => $this->input('status', $this->input('estado')),
            'request_id' => $this->input('request_id', $this->input('solicitud')),
            'code' => $this->input('code', $this->input('cod')),
            'quote_date' => $this->input('quote_date', $this->input('fecha_cotiz')),
            'observation' => $this->input('observation', $this->input('observacion')),
            'date' => $this->input('date', $this->input('fecha')),
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
            'unit_measure_id' => ['required', 'integer', 'exists:unidad_medida,id_unidad_medida'],
            'price' => ['required', 'numeric', 'min:0'],
            'type_id' => ['required', 'integer', 'exists:tipo_insumo,id_tipo'],
            'status' => ['required', 'string', 'size:2', 'in:AC,DC,DP'],
            'request_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:30'],
            'quote_date' => ['required', 'date'],
            'observation' => ['nullable', 'string', 'max:300'],
            'date' => ['nullable', 'date'],
        ];
    }
}
