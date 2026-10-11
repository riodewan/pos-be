<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\CreateSupplierRequest;
use App\Modules\Catalog\Http\Requests\UpdateSupplierRequest;
use App\Modules\Catalog\Http\Resources\SupplierResource;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CatalogService $catalogService
    ) {}

    public function index(): JsonResponse
    {
        $suppliers = $this->catalogService->listSuppliers();

        return $this->success(SupplierResource::collection($suppliers));
    }

    public function store(CreateSupplierRequest $request): JsonResponse
    {
        $supplier = $this->catalogService->createSupplier($request->validated());

        return $this->success(new SupplierResource($supplier), 'Supplier created successfully.', Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $supplier = $this->catalogService->getSupplier($id);

        return $this->success(new SupplierResource($supplier));
    }

    public function update(UpdateSupplierRequest $request, string $id): JsonResponse
    {
        $supplier = $this->catalogService->getSupplier($id);
        $updated = $this->catalogService->updateSupplier($supplier, $request->validated());

        return $this->success(new SupplierResource($updated), 'Supplier updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $supplier = $this->catalogService->getSupplier($id);
        $this->catalogService->deleteSupplier($supplier);

        return $this->success(null, 'Supplier deleted successfully.');
    }
}
