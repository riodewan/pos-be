<?php

namespace App\Modules\RecipeMenu\Http\Requests;

use App\Core\Enums\CommonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['nullable', new Enum(CommonStatus::class)],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required_with:items', 'string', 'exists:ingredients,id'],
            'items.*.unit_id' => ['required_with:items', 'string', 'exists:units,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
        ];
    }
}
