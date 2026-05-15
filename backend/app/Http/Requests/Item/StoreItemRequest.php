<?php

namespace App\Http\Requests\Item;

use App\Models\SubgroupCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
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
        return [
            'group_id' => ['required', 'integer', 'exists:grupo,id_grupo'],
            'subgroup_id' => ['required', 'integer', 'exists:sub_grupo,id_subgrupo'],
            'item' => [
                'required',
                'string',
                'max:100',
                Rule::unique('item', 'item'),
            ],
            'unit_measure_id' => ['required', 'integer', 'exists:unidad_medida,id_unidad_medida'],
            'status' => ['required', 'string', 'size:2', 'in:AC,DC'],
            'code' => ['nullable', 'string', 'max:30'],
            'specification' => ['nullable', 'string', 'max:200'],
            'sheet' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('group_id') || ! $this->filled('subgroup_id')) {
                return;
            }

            $belongsToGroup = SubgroupCatalog::query()
                ->where('id_subgrupo', (int) $this->integer('subgroup_id'))
                ->where('id_grupo', (int) $this->integer('group_id'))
                ->exists();

            if (! $belongsToGroup) {
                $validator->errors()->add('subgroup_id', 'El subgrupo no pertenece al grupo seleccionado.');
            }
        });
    }
}
