<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item' => ['required', 'string', 'max:100'],
            'specification_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'sheet_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
