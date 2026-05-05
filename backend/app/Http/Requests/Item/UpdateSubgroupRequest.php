<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubgroupRequest extends FormRequest
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
            'id_grupo' => ['required', 'integer', 'exists:grupo,id_grupo'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
