<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class IndexSubgroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', 'exists:grupo,id_grupo'],
        ];
    }
}
