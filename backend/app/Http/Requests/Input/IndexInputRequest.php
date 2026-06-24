<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class IndexInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:100'],
            'type_id' => ['nullable', 'integer', 'exists:tipo_insumo,id_tipo'],
            'category_id' => ['nullable', 'integer', 'exists:categoria_insumo,id_categoria'],
            'unit_measure_id' => ['nullable', 'integer', 'exists:unidad_medida,id_unidad_medida'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC,DP'],
            'order' => ['nullable', 'string', 'in:legacy,recent,oldest'],
            'duplicates' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
