<?php

namespace App\Modules\RecipeMenu\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
