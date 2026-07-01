<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'funcionario' => is_string($this->funcionario) ? trim($this->funcionario) : $this->funcionario,
            'ci' => is_string($this->ci) ? trim($this->ci) : $this->ci,
            'username' => is_string($this->username) ? strtoupper(trim($this->username)) : $this->username,
            'estado' => is_string($this->estado) ? strtoupper(trim($this->estado)) : $this->estado,
            'unit_description' => is_string(data_get($this->input('unidad'), 'descripcion'))
                ? trim((string) data_get($this->input('unidad'), 'descripcion'))
                : $this->input('unit_description'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'funcionario' => ['required', 'string', 'max:80'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('usuario', 'ci')],
            'username' => ['required', 'string', 'max:50', Rule::unique('usuario', 'username')],
            'password' => ['required', 'string', 'confirmed', 'min:8', 'max:100'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
            'role_id' => ['required', 'integer', 'exists:rol,id_rol'],
            'unit_id' => ['nullable', 'integer', 'exists:unidad,id_unidad'],
            'unit_description' => ['nullable', 'string', 'max:255'],
            'unidad' => ['nullable', 'array'],
            'unidad.descripcion' => ['nullable', 'string', 'max:255'],
            'item' => ['nullable', 'integer'],
            'subalcaldia' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'La contrasena es obligatoria.',
            'password.confirmed' => 'La confirmacion no coincide.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
            'password.max' => 'La contrasena no debe superar los 100 caracteres.',
            'ci.unique' => 'Ya existe un usuario registrado con ese C.I.',
            'username.unique' => 'Ya existe un usuario con ese nombre de usuario.',
        ];
    }
}
