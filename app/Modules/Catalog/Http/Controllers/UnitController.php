<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Enums\UnitType;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\CreateUnitRequest;
use App\Modules\Catalog\Http\Resources\UnitResource;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
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

    public function update(Request $request, string $id): JsonResponse
    {
        $unit = $this->catalogService->getUnit($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('units', 'name')->ignore($unit->id)],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('units', 'code')->ignore($unit->id)],
            'type' => ['sometimes', 'required', new Enum(UnitType::class)],
        ]);

        $updated = $this->catalogService->updateUnit($unit, $validated);

        return $this->success(new UnitResource($updated), 'Unit updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $unit = $this->catalogService->getUnit($id);
        $this->catalogService->deleteUnit($unit);

        return $this->success(null, 'Unit deleted successfully.');
    }
}
