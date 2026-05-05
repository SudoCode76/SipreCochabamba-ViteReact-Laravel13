<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalculationPercentageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        if (is_string($this->input('codigo'))) {
            $data['codigo'] = trim((string) $this->input('codigo'));
        }

        if (is_string($this->input('descripcion'))) {
            $data['descripcion'] = trim((string) $this->input('descripcion'));
        }

        if (is_string($this->input('observacion'))) {
            $data['observacion'] = trim((string) $this->input('observacion'));
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30'],
            'descripcion' => ['required', 'string', 'max:100'],
            'porcentaje' => ['required', 'numeric'],
            'observacion' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
