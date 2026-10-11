<?php

namespace App\Modules\RecipeMenu\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\RecipeMenu\Http\Requests\CreateRecipeRequest;
use App\Modules\RecipeMenu\Http\Requests\UpdateRecipeRequest;
use App\Modules\RecipeMenu\Http\Resources\RecipeResource;
use App\Modules\RecipeMenu\Services\RecipeService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RecipeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RecipeService $recipeService
    ) {}

    public function index(): JsonResponse
    {
        $recipes = $this->recipeService->listRecipes();

        return $this->success(RecipeResource::collection($recipes));
    }

    public function store(CreateRecipeRequest $request): JsonResponse
    {
        try {
            $recipe = $this->recipeService->createRecipe($request->validated());
            return $this->success(new RecipeResource($recipe), 'Recipe created successfully.', Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function show(string $id): JsonResponse
    {
        $recipe = $this->recipeService->getRecipe($id);

        return $this->success(new RecipeResource($recipe));
    }

    public function update(UpdateRecipeRequest $request, string $id): JsonResponse
    {
        $recipe = $this->recipeService->getRecipe($id);

        try {
            $updated = $this->recipeService->updateRecipe($recipe, $request->validated());
            return $this->success(new RecipeResource($updated), 'Recipe updated successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $recipe = $this->recipeService->getRecipe($id);
        $this->recipeService->deleteRecipe($recipe);

        return $this->success(null, 'Recipe deleted successfully.');
    }
}
