<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\CreateProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CatalogService $catalogService
    ) {}

    public function index(): JsonResponse
    {
        $products = $this->catalogService->listProducts();

        return $this->success(ProductResource::collection($products));
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->catalogService->createProduct($request->validated());

        return $this->success(new ProductResource($product), 'Product created successfully.', Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $product = $this->catalogService->getProduct($id);

        return $this->success(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = $this->catalogService->getProduct($id);
        $updated = $this->catalogService->updateProduct($product, $request->validated());

        return $this->success(new ProductResource($updated), 'Product updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $product = $this->catalogService->getProduct($id);
        $this->catalogService->deleteProduct($product);

        return $this->success(null, 'Product deleted successfully.');
    }
}
