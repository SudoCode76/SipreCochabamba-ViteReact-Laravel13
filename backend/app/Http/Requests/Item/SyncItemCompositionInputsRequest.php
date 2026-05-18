<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class SyncItemCompositionInputsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.id_insumo' => ['required', 'integer', 'exists:insumo,id_insumo'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'deleted_input_ids' => ['sometimes', 'array'],
            'deleted_input_ids.*' => ['integer', 'exists:insumo,id_insumo'],
        ];
    }
}
