<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\CreatePurchaseRequest;
use App\Modules\Inventory\Http\Resources\PurchaseResource;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(): JsonResponse
    {
        $purchases = $this->inventoryService->listPurchases();

        return $this->success(PurchaseResource::collection($purchases));
    }

    public function store(CreatePurchaseRequest $request): JsonResponse
    {
        try {
            $purchase = $this->inventoryService->createPurchase($request->validated(), $request->user()?->id);
            return $this->success(new PurchaseResource($purchase), 'Purchase order created successfully.', Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function show(string $id): JsonResponse
    {
        $purchase = $this->inventoryService->getPurchase($id);

        return $this->success(new PurchaseResource($purchase));
    }

    public function receive(Request $request, string $id): JsonResponse
    {
        $purchase = $this->inventoryService->getPurchase($id);

        try {
            $received = $this->inventoryService->receivePurchase($purchase, $request->user()?->id);
            return $this->success(new PurchaseResource($received), 'Purchase order received and stock updated successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function cancel(string $id): JsonResponse
    {
        $purchase = $this->inventoryService->getPurchase($id);

        try {
            $cancelled = $this->inventoryService->cancelPurchase($purchase);
            return $this->success(new PurchaseResource($cancelled), 'Purchase order cancelled successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
