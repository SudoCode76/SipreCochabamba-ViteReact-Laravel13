<?php

namespace App\Http\Requests\RolePermission;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'function_ids' => ['required', 'array'],
            'function_ids.*' => ['integer', 'distinct', 'exists:funcion,id_funcion'],
        ];
    }
}
