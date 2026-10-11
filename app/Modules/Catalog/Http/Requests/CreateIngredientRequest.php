<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = AppContext::storeId();

        return [
            'unit_id' => ['required', 'string', 'exists:units,id'],
            'name' => ['required', 'string', 'max:100'],
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('ingredients', 'sku')
                    ->where('store_id', $storeId)
                    ->whereNull('deleted_at'),
            ],
            'status' => ['nullable', new Enum(CommonStatus::class)],
        ];
    }
}
