<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_modulo' => ['required', 'string', 'max:150'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
