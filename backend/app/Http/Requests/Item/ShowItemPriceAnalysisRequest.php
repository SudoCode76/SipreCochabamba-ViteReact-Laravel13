<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class ShowItemPriceAnalysisRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('mode')) {
            $this->merge([
                'mode' => 'general',
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
            'mode' => ['nullable', 'string', 'in:general,fndr,upre,fps,obras,proman'],
        ];
    }
}
