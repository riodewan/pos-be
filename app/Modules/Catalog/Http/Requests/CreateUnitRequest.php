<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Core\Enums\UnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:units,name'],
            'code' => ['required', 'string', 'max:20', 'unique:units,code'],
            'type' => ['required', new Enum(UnitType::class)],
        ];
    }
}
