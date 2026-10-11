<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = AppContext::storeId();
        $ingredientId = $this->route('ingredient') ?: $this->route('id');

        return [
            'unit_id' => ['sometimes', 'required', 'string', 'exists:units,id'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('ingredients', 'sku')
                    ->where('store_id', $storeId)
                    ->whereNull('deleted_at')
                    ->ignore($ingredientId),
            ],
            'status' => ['nullable', new Enum(CommonStatus::class)],
        ];
    }
}
