<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre_proyecto'))) {
            $this->merge([
                'nombre_proyecto' => strtoupper(trim((string) $this->input('nombre_proyecto'))),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_proyecto' => ['required', 'string', 'max:500', 'unique:proyecto,nombre_proyecto'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_proyecto.unique' => 'Ya existe un proyecto o planilla con el mismo nombre.',
        ];
    }
}
