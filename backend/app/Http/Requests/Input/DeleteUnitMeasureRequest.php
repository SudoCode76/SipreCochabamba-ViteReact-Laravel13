<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'autorizacion' => ['required', 'string', 'max:100'],
        ];
    }
}
