<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WastageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'wastage_number' => $this->wastage_number,
            'wastage_date' => $this->wastage_date?->format('Y-m-d'),
            'status' => $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'items' => WastageItemResource::collection($this->whenLoaded('wastageItems')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
