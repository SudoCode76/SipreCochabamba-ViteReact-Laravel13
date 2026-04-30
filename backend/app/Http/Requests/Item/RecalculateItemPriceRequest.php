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
            'mode' => ['required', 'string', 'in:fndr,upre'],
            'fecha' => ['required', 'date'],
        ];
    }
}
