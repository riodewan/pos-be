<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\CreateWastageRequest;
use App\Modules\Inventory\Http\Resources\WastageResource;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class WastageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(): JsonResponse
    {
        $wastages = $this->inventoryService->listWastages();

        return $this->success(WastageResource::collection($wastages));
    }

    public function store(CreateWastageRequest $request): JsonResponse
    {
        try {
            $wastage = $this->inventoryService->createWastage($request->validated(), $request->user()?->id);
            return $this->success(new WastageResource($wastage), 'Wastage recorded successfully.', Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
