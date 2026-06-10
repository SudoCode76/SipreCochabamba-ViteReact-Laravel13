<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class IndexFndrItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'exists:grupo,id_grupo'],
            'subgroup_id' => ['nullable', 'integer', 'exists:sub_grupo,id_subgrupo'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC'],
            'order' => ['nullable', 'string', 'in:legacy,recent,oldest,missing_specifications'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
