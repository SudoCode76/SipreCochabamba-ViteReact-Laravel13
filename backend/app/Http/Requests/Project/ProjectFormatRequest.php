<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(['PCA', 'PC_OBRAS', 'PC_FPS', 'PC_FNDR', 'PC_UPRE', 'PC_PROMAN'])],
        ];
    }
}
