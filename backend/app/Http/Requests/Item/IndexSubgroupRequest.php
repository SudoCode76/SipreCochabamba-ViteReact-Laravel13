<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class IndexSubgroupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('group_id')) && trim((string) $this->input('group_id')) === '') {
            $this->merge([
                'group_id' => null,
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => ['nullable', 'integer', 'exists:grupo,id_grupo'],
        ];
    }
}
