<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'nombre_proyecto' => ['required', 'string', 'max:500', Rule::unique('proyecto', 'nombre_proyecto')->ignore($project->id_proyecto, 'id_proyecto')],
            'fecha' => ['required', 'date'],
            'ubicacion' => ['required', 'string', 'max:100'],
            'latitud' => ['nullable', 'string', 'max:50'],
            'longitud' => ['nullable', 'string', 'max:50'],
            'responsable' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'solicitante' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'observaciones' => ['required', 'string', 'max:500'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
            'aprobado' => ['required', 'string', 'size:2', 'in:PD,RV,AP'],
            'fecha_aprob' => ['nullable', 'date'],
            'distrito' => ['nullable', 'string', 'max:50'],
            'zona' => ['nullable', 'string', 'max:150'],
            'otb' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_proyecto.unique' => 'Ya existe un proyecto con el mismo nombre.',
        ];
    }
}
