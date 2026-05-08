<?php

namespace App\Http\Requests\Function;

use Illuminate\Foundation\Http\FormRequest;

class IndexFunctionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'class' => $this->input('class', $this->input('clase', $this->input('controlador'))),
            'status' => $this->normalizeStatus($this->input('status', $this->input('estado'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'class' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
