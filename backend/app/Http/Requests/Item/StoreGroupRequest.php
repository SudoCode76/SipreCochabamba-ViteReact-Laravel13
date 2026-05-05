<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        if (is_string($this->input('codigo_grupo'))) {
            $data['codigo_grupo'] = trim((string) $this->input('codigo_grupo'));
        }

        if (is_string($this->input('nombre_grupo'))) {
            $data['nombre_grupo'] = trim((string) $this->input('nombre_grupo'));
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
            'codigo_grupo' => ['required', 'string', 'max:30'],
            'nombre_grupo' => ['required', 'string', 'max:100'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
