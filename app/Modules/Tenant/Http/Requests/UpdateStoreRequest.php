<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = AppContext::tenantId();
        $storeId = $this->route('store') ?: $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('stores', 'code')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($storeId),
            ],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'status' => ['nullable', new Enum(CommonStatus::class)],
        ];
    }
}
