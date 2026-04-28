<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_rol' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rol', 'nombre_rol'),
            ],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
