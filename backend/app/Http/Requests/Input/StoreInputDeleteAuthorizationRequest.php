<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputDeleteAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nro_autorizacion' => ['nullable', 'string', 'max:100'],
        ];
    }
}
