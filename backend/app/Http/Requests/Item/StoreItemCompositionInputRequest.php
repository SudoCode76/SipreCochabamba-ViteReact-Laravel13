<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemCompositionInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_insumo' => ['required', 'integer', 'exists:insumo,id_insumo'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
