<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class RecalculateItemPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:general,fndr,upre,fps,obras,proman'],
            'fecha' => ['required', 'date'],
        ];
    }
}
