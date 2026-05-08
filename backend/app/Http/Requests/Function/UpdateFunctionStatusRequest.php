<?php

namespace App\Http\Requests\Function;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFunctionStatusRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
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
