<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInputStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'size:2', 'in:AC,DC,DP'],
        ];
    }
}
