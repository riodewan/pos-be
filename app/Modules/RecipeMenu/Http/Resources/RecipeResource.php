<?php

namespace App\Modules\RecipeMenu\Http\Resources;

use App\Modules\Catalog\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'name' => $this->name,
            'status' => $this->status,
            'items' => RecipeItemResource::collection($this->whenLoaded('recipeItems')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
