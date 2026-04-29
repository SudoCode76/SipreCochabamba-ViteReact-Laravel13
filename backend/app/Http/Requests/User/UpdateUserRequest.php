<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'funcionario' => is_string($this->funcionario) ? trim($this->funcionario) : $this->funcionario,
            'ci' => is_string($this->ci) ? trim($this->ci) : $this->ci,
            'username' => is_string($this->username) ? strtoupper(trim($this->username)) : $this->username,
            'estado' => is_string($this->estado) ? strtoupper(trim($this->estado)) : $this->estado,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'funcionario' => ['required', 'string', 'max:80'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('usuario', 'ci')->ignore($user->id_usuario, 'id_usuario')],
            'username' => ['required', 'string', 'max:50', Rule::unique('usuario', 'username')->ignore($user->id_usuario, 'id_usuario')],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
            'role_id' => ['required', 'integer', 'exists:rol,id_rol'],
            'unit_id' => ['nullable', 'integer', 'exists:unidad,id_unidad'],
            'item' => ['nullable', 'integer'],
            'subalcaldia' => ['nullable', 'integer'],
        ];
    }
}
