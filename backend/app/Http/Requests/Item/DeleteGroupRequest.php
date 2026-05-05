<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class DeleteGroupRequest extends FormRequest
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
