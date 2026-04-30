<?php

namespace App\Http\Requests\Function;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFunctionStatusRequest extends FormRequest
{
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
