<?php

namespace App\Http\Requests\Input;

class UpdateInputPriceRequest extends UpdateInputRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'quote_ids' => ['nullable', 'array'],
            'quote_ids.*' => ['integer'],
            'valido' => ['nullable', 'file', 'mimes:pdf', 'max:4096'],
            'propuesto_1' => ['nullable', 'file', 'mimes:pdf', 'max:4096'],
            'propuesto_2' => ['nullable', 'file', 'mimes:pdf', 'max:4096'],
            'propuesto_3' => ['nullable', 'file', 'mimes:pdf', 'max:4096'],
        ]);
    }
}
