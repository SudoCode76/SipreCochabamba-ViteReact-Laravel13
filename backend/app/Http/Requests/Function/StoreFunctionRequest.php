<?php

namespace App\Http\Requests\Function;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_funcion' => [
                'required',
                'string',
                'max:100',
                Rule::unique('funcion', 'nombre_funcion'),
            ],
            'descripcion' => ['required', 'string', 'max:50'],
            'clase' => ['required', 'string', 'max:30'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
