<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Catalog\Http\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'purchase_number' => $this->purchase_number,
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'items' => PurchaseItemResource::collection($this->whenLoaded('purchaseItems')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
