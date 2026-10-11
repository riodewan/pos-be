<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWastageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wastage_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'items.*.unit_id' => ['required', 'string', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
