<?php

namespace App\Modules\RecipeMenu\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\RecipeMenu\Http\Requests\CreateMenuRequest;
use App\Modules\RecipeMenu\Http\Requests\UpdateMenuRequest;
use App\Modules\RecipeMenu\Http\Resources\MenuResource;
use App\Modules\RecipeMenu\Services\MenuService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class MenuController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MenuService $menuService
    ) {}

    public function index(): JsonResponse
    {
        $menus = $this->menuService->listMenus();

        return $this->success(MenuResource::collection($menus));
    }

    public function store(CreateMenuRequest $request): JsonResponse
    {
        try {
            $menu = $this->menuService->createMenu($request->validated());
            return $this->success(new MenuResource($menu), 'Menu created successfully.', Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function show(string $id): JsonResponse
    {
        $menu = $this->menuService->getMenu($id);

        return $this->success(new MenuResource($menu));
    }

    public function update(UpdateMenuRequest $request, string $id): JsonResponse
    {
        $menu = $this->menuService->getMenu($id);

        try {
            $updated = $this->menuService->updateMenu($menu, $request->validated());
            return $this->success(new MenuResource($updated), 'Menu updated successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $menu = $this->menuService->getMenu($id);
        $this->menuService->deleteMenu($menu);

        return $this->success(null, 'Menu deleted successfully.');
    }
}
