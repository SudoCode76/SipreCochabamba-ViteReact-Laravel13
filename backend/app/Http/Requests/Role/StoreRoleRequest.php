<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre_rol' => $this->input('nombre_rol', $this->input('name', $this->input('role'))),
            'estado' => $this->normalizeStatus($this->input('estado', $this->input('status'))),
        ]);
    }

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

    private function normalizeStatus(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtoupper(trim($value))) {
            'ACTIVO' => 'AC',
            'INACTIVO' => 'DC',
            default => strtoupper(trim($value)),
        };
    }
}
