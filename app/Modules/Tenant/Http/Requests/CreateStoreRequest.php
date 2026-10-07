<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Core\Context\AppContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = AppContext::tenantId();

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('stores', 'code')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ];
    }
}
