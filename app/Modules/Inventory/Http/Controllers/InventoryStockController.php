<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Http\Resources\InventoryStockResource;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class InventoryStockController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(): JsonResponse
    {
        $stocks = $this->inventoryService->listStocks();

        return $this->success(InventoryStockResource::collection($stocks));
    }

    public function show(string $ingredientId): JsonResponse
    {
        $stock = $this->inventoryService->getStock($ingredientId);

        return $this->success(new InventoryStockResource($stock));
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        try {
            $stock = $this->inventoryService->adjustStock(
                $request->validated()['ingredient_id'],
                $request->validated()['new_quantity'],
                $request->validated()['notes'],
                $request->user()?->id
            );

            return $this->success(new InventoryStockResource($stock), 'Stock adjusted successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
