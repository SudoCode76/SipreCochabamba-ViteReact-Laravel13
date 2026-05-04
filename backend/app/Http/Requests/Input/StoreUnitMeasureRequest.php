<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitMeasureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        if (is_string($this->input('descripcion'))) {
            $data['descripcion'] = trim((string) $this->input('descripcion'));
        }

        if (is_string($this->input('abreviatura'))) {
            $data['abreviatura'] = trim((string) $this->input('abreviatura'));
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
            'descripcion' => ['required', 'string', 'max:30'],
            'abreviatura' => ['required', 'string', 'max:50'],
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
