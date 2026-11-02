<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\CreateIngredientRequest;
use App\Modules\Catalog\Http\Requests\UpdateIngredientRequest;
use App\Modules\Catalog\Http\Resources\IngredientResource;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IngredientController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CatalogService $catalogService
    ) {}

    public function index(): JsonResponse
    {
        $ingredients = $this->catalogService->listIngredients();

        return $this->success(IngredientResource::collection($ingredients));
    }

    public function store(CreateIngredientRequest $request): JsonResponse
    {
        $ingredient = $this->catalogService->createIngredient($request->validated());

        return $this->success(new IngredientResource($ingredient), 'Ingredient created successfully.', Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $ingredient = $this->catalogService->getIngredient($id);

        return $this->success(new IngredientResource($ingredient));
    }

    public function update(UpdateIngredientRequest $request, string $id): JsonResponse
    {
        $ingredient = $this->catalogService->getIngredient($id);
        $updated = $this->catalogService->updateIngredient($ingredient, $request->validated());

        return $this->success(new IngredientResource($updated), 'Ingredient updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $ingredient = $this->catalogService->getIngredient($id);
        $this->catalogService->deleteIngredient($ingredient);

        return $this->success(null, 'Ingredient deleted successfully.');
    }

    public function categories(): JsonResponse
    {
        $categories = $this->catalogService->listIngredientCategories();

        return $this->success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category = $this->catalogService->saveIngredientCategory($validated);

        return $this->success($category, 'Kategori bahan berhasil ditambahkan.', Response::HTTP_CREATED);
    }

    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category = $this->catalogService->saveIngredientCategory($validated, $id);

        return $this->success($category, 'Kategori bahan berhasil diperbarui.');
    }

    public function destroyCategory(string $id): JsonResponse
    {
        $this->catalogService->deleteIngredientCategory($id);

        return $this->success(null, 'Kategori bahan berhasil dihapus.');
    }
}
