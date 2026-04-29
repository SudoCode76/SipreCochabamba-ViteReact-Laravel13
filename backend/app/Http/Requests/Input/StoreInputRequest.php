<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputRequest extends FormRequest
{
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
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC,DP'],
            'request_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:30'],
            'quote_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string', 'max:300'],
        ];
    }
}
