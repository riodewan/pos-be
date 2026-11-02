<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Http\Resources\InventoryMovementResource;
use App\Modules\Inventory\Http\Resources\InventoryStockResource;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function stockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $movement = $this->inventoryService->recordStockIn($validated, $request->user()?->id);

            return $this->success(new InventoryMovementResource($movement), 'Stok masuk berhasil dicatat.', Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
