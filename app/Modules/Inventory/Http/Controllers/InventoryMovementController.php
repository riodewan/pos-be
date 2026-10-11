<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Resources\InventoryMovementResource;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $movements = $this->inventoryService->listMovements(
            $request->query('ingredient_id'),
            $request->query('type')
        );

        return $this->success(InventoryMovementResource::collection($movements)->response()->getData(true));
    }
}
