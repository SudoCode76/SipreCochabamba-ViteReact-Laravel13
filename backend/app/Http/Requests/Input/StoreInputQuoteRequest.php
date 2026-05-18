<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputQuoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'condition' => $this->input('condition', $this->input('condicion')),
            'status' => $this->input('status', $this->input('estado')),
            'log_id' => $this->input('log_id', $this->input('id_log_insumo')),
            'date' => $this->input('date', $this->input('fecha')),
            'request_id' => $this->input('request_id', $this->input('id_solicitud')),
        ]);
    }

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
            'file' => ['nullable'],
            'date' => ['nullable', 'date'],
            'file_1' => ['nullable'],
            'file_2' => ['nullable'],
            'valido' => ['nullable', 'file', 'mimes:pdf', 'max:4096', 'required_without_all:propuesto_1,propuesto_2'],
            'propuesto_1' => ['nullable', 'file', 'mimes:pdf', 'max:4096', 'required_without_all:valido,propuesto_2'],
            'propuesto_2' => ['nullable', 'file', 'mimes:pdf', 'max:4096', 'required_without_all:valido,propuesto_1'],
            'request_id' => ['nullable', 'integer'],
        ];
    }
}
