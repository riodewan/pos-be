<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Context\AppContext;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Tenant\Http\Requests\CreateStoreRequest;
use App\Modules\Tenant\Http\Requests\UpdateStoreRequest;
use App\Modules\Tenant\Http\Resources\StoreResource;
use App\Modules\Tenant\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class StoreController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StoreService $storeService
    ) {}

    /**
     * List all stores for current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = AppContext::tenant();
        $stores = $this->storeService->listStores($tenant, $request->user());

        return $this->success(StoreResource::collection($stores));
    }

    /**
     * Create a new store.
     */
    public function store(CreateStoreRequest $request): JsonResponse
    {
        $tenant = AppContext::tenant();
        $store = $this->storeService->createStore($tenant, $request->validated());

        return $this->success(new StoreResource($store), 'Store created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Show single store details.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = AppContext::tenant();
        $store = $this->storeService->getStore($tenant, $id);

        return $this->success(new StoreResource($store));
    }

    /**
     * Update an existing store.
     */
    public function update(UpdateStoreRequest $request, string $id): JsonResponse
    {
        $tenant = AppContext::tenant();
        $store = $this->storeService->getStore($tenant, $id);
        $updated = $this->storeService->updateStore($store, $request->validated());

        return $this->success(new StoreResource($updated), 'Store updated successfully.');
    }

    /**
     * Soft delete store.
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = AppContext::tenant();
        $store = $this->storeService->getStore($tenant, $id);

        try {
            $this->storeService->deleteStore($tenant, $store);
            return $this->success(null, 'Store deleted successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
