<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fields = ['nombre_proyecto', 'ubicacion', 'observaciones', 'distrito', 'zona', 'otb'];
        $data = [];

        foreach ($fields as $field) {
            if (is_string($this->input($field))) {
                $data[$field] = strtoupper(trim((string) $this->input($field)));
            }
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
            'nombre_proyecto' => ['required', 'string', 'max:500', 'unique:proyecto,nombre_proyecto'],
            'fecha' => ['required', 'date'],
            'ubicacion' => ['required', 'string', 'max:100'],
            'latitud' => ['nullable', 'string', 'max:50'],
            'longitud' => ['nullable', 'string', 'max:50'],
            'responsable' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'solicitante' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
            'aprobado' => ['required', 'string', 'size:2', 'in:PD'],
            'fecha_aprob' => ['nullable', 'date'],
            'distrito' => ['nullable', 'string', 'max:50'],
            'zona' => ['nullable', 'string', 'max:150'],
            'otb' => ['nullable', 'string', 'max:150'],
            'signature_access' => ['nullable', 'array'],
            'signature_access.mode' => ['required_with:signature_access', 'string', 'in:selected,all'],
            'signature_access.user_ids' => ['nullable', 'array'],
            'signature_access.user_ids.*' => ['integer', 'distinct', 'exists:usuario,id_usuario'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_proyecto.unique' => 'Ya existe un proyecto con el mismo nombre.',
        ];
    }
}
