<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC'],
            'role_id' => ['nullable', 'integer', 'exists:rol,id_rol'],
            'unit_id' => ['nullable', 'integer', 'exists:unidad,id_unidad'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
