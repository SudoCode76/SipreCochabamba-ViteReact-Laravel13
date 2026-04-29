<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC,DP'],
            'log_id' => ['nullable', 'integer', 'exists:log_insumo,id_log'],
            'file' => ['nullable', 'string', 'max:180'],
            'date' => ['nullable', 'date'],
            'file_1' => ['nullable', 'string', 'max:180'],
            'file_2' => ['nullable', 'string', 'max:180'],
            'request_id' => ['nullable', 'integer'],
        ];
    }
}
