<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'estado' => is_string($this->estado) ? strtoupper(trim($this->estado)) : $this->estado,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'size:2', 'in:AC,DC'],
        ];
    }
}
