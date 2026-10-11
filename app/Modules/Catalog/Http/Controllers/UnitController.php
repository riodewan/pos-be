<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\CreateUnitRequest;
use App\Modules\Catalog\Http\Resources\UnitResource;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CatalogService $catalogService
    ) {}

    public function index(): JsonResponse
    {
        $units = $this->catalogService->listUnits();

        return $this->success(UnitResource::collection($units));
    }

    public function store(CreateUnitRequest $request): JsonResponse
    {
        $unit = $this->catalogService->createUnit($request->validated());

        return $this->success(new UnitResource($unit), 'Unit created successfully.', Response::HTTP_CREATED);
    }
}
