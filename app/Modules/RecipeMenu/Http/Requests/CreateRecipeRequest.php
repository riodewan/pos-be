<?php

namespace App\Modules\RecipeMenu\Http\Requests;

use App\Core\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', 'exists:products,id'],
            'name' => ['required', 'string', 'max:100'],
            'status' => ['nullable', new Enum(CommonStatus::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'items.*.unit_id' => ['required', 'string', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
