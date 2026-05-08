<?php

namespace App\Http\Requests\Authorization;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthorizationStatusRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'estado' => $this->normalizeStatus($this->input('estado', $this->input('status'))),
            'nro_autorizacion' => $this->input('nro_autorizacion', $this->input('authorization_number')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'size:2', 'in:AP,NP'],
            'nro_autorizacion' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function normalizeStatus(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (strtoupper(trim($value))) {
            'AUTORIZADO' => 'AP',
            'NO PROCEDE' => 'NP',
            default => strtoupper(trim($value)),
        };
    }
}
