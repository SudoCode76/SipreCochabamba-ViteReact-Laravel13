<?php

namespace App\Http\Requests\Authorization;

use Illuminate\Foundation\Http\FormRequest;

class IndexAuthorizationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->normalizeStatus($this->input('status', $this->input('estado'))),
            'module' => $this->input('module', $this->input('modulo')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'size:2', 'in:PE,AP,NP'],
            'module' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function normalizeStatus(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtoupper(trim($value))) {
            'PENDIENTE' => 'PE',
            'AUTORIZADO' => 'AP',
            'NO PROCEDE' => 'NP',
            default => strtoupper(trim($value)),
        };
    }
}
