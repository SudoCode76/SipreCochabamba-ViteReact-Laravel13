<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'item' => is_string($this->item) ? strtoupper(trim($this->item)) : $this->item,
            'status' => is_string($this->status) ? strtoupper(trim($this->status)) : $this->status,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $itemId = is_object($item) ? (int) $item->id_item : (int) $item;

        return [
            'item' => [
                'required',
                'string',
                'max:100',
                Rule::unique('item', 'item')->where(fn ($query) => $query
                    ->where('grupo', (int) $this->route('item')->grupo)
                    ->where('subgrupo', (int) $this->route('item')->subgrupo))
                    ->ignore($itemId, 'id_item'),
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC'],
            'specification' => ['nullable', 'string', 'max:200'],
            'sheet' => ['nullable', 'string', 'max:200'],
            'specification_file' => ['nullable', 'file', 'max:10240'],
            'sheet_file' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
