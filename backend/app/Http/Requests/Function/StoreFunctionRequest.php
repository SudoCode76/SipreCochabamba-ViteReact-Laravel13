<?php

namespace App\Http\Requests\Function;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFunctionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre_funcion' => $this->input('nombre_funcion', $this->input('name')),
            'descripcion' => $this->input('descripcion', $this->input('description')),
            'clase' => $this->input('clase', $this->input('class', $this->input('controlador'))),
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
