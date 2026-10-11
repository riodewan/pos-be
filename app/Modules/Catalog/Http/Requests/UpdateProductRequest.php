<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = AppContext::storeId();
        $productId = $this->route('product') ?: $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')
                    ->where('store_id', $storeId)
                    ->whereNull('deleted_at')
                    ->ignore($productId),
            ],
            'description' => ['nullable', 'string'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['nullable', new Enum(CommonStatus::class)],
        ];
    }
}
