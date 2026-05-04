<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class BudgetRecalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
        ];
    }
}
