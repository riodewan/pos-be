<?php

namespace App\Modules\RecipeMenu\Http\Requests;

use App\Core\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', new Enum(CommonStatus::class)],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ];
    }
}
