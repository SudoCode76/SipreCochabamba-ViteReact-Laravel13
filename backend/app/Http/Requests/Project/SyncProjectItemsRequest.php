<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProjectItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.id_proyecto_item' => ['nullable', 'integer', 'exists:proyecto_item,id_proyecto_item'],
            'items.*.id_item' => ['required', 'integer', 'exists:item,id_item'],
            'items.*.id_modulo' => ['nullable', 'integer', 'exists:modulo,id_modulo'],
            'items.*.precio' => ['required', 'numeric', 'min:0'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0'],
            'items.*.prioridad' => ['nullable', 'integer', 'min:1'],
            'items.*.estado' => ['nullable', 'string', 'size:2', 'in:AC,DC'],
            'format' => ['nullable', 'string', Rule::in(['PCA', 'PC_OBRAS', 'PC_FPS', 'PC_FNDR', 'PC_UPRE', 'PC_PROMAN'])],
        ];
    }
}
