<?php

namespace App\Http\Requests\InputRequest;

use Illuminate\Foundation\Http\FormRequest;

class RevertInputSolicitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'observation' => $this->input('observation', $this->input('observacion')),
            'revert_user_id' => $this->input('revert_user_id', $this->input('usuario_rev')),
            'revert_date' => $this->input('revert_date', $this->input('fecha_rev')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observation' => ['required', 'string', 'max:500'],
            'revert_user_id' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'revert_date' => ['required', 'date'],
        ];
    }
}
