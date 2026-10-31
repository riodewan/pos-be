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
            'unit_id' => ['nullable', 'string', 'exists:units,id'],
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
            'category' => ['nullable', 'string', 'max:50'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'supplier_id' => ['nullable', 'string', 'exists:suppliers,id'],
            'storage_location' => ['nullable', 'string', 'max:100'],
            'shelf_life_value' => ['nullable', 'integer', 'min:0'],
            'shelf_life_unit' => ['nullable', 'string', 'max:20'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }
}
