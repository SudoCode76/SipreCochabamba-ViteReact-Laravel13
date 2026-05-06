<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserUnitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_description' => is_string(data_get($this->input('unidad'), 'descripcion'))
                ? trim((string) data_get($this->input('unidad'), 'descripcion'))
                : $this->input('unit_description'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['nullable', 'integer', 'exists:unidad,id_unidad'],
            'unit_description' => ['nullable', 'string', 'max:255'],
            'unidad' => ['nullable', 'array'],
            'unidad.descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
