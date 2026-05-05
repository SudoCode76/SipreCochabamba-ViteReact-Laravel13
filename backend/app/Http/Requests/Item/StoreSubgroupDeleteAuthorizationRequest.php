<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubgroupDeleteAuthorizationRequest extends FormRequest
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
