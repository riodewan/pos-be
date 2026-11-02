<?php

namespace App\Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:6'],
            'role_id' => ['nullable', 'string'],
            'role_name' => ['nullable', 'string', 'max:100'],
            'store_id' => ['nullable', 'string', 'exists:stores,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
