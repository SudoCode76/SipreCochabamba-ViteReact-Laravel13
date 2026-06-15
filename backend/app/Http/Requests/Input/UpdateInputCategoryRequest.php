<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInputCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('descripcion'))) {
            $this->merge([
                'descripcion' => trim((string) $this->input('descripcion')),
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
            'descripcion' => ['required', 'string', 'max:80'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
