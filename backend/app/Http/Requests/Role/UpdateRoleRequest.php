<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'nombre_rol' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rol', 'nombre_rol')->ignore($role->id_rol, 'id_rol'),
            ],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
