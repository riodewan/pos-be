<?php

namespace App\Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.role_id' => ['required', 'string', 'exists:roles,id'],
            'roles.*.store_id' => ['nullable', 'string', 'exists:stores,id'],
        ];
    }
}
