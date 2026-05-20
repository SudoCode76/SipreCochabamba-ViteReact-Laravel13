<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class IndexProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC'],
            'approval_status' => ['nullable', 'string', 'size:2', 'in:PD,RV,AP'],
            'responsable_id' => ['nullable', 'integer', 'exists:usuario,id_usuario'],
            'solicitante_id' => ['nullable', 'integer', 'exists:usuario,id_usuario'],
            'order' => ['nullable', 'string', 'in:legacy,recent,oldest'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
