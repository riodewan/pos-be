<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'new_quantity' => ['required', 'numeric', 'min:0'],
            'notes' => ['required', 'string', 'max:255'],
        ];
    }
}
