<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\TableStatus;
use App\Modules\PosOrder\Models\Table;
use App\Modules\PosOrder\Services\TableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TableController extends Controller
{
    public function __construct(
        protected TableService $tableService
    ) {}

    public function index(): JsonResponse
    {
        $tables = $this->tableService->listTables();

        return response()->json([
            'message' => 'Tables retrieved successfully.',
            'data' => $tables,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(TableStatus::class)],
        ]);

        try {
            $table = $this->tableService->createTable($validated);

            return response()->json([
                'message' => 'Table created successfully.',
                'data' => $table,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $id): JsonResponse
    {
        $table = $this->tableService->getTable($id);

        return response()->json([
            'message' => 'Table retrieved successfully.',
            'data' => $table,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $table = Table::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'code' => ['sometimes', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(TableStatus::class)],
        ]);

        try {
            $table = $this->tableService->updateTable($table, $validated);

            return response()->json([
                'message' => 'Table updated successfully.',
                'data' => $table,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);

        try {
            $this->tableService->deleteTable($table);

            return response()->json([
                'message' => 'Table deleted successfully.',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function generateQr(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $qrCode = $this->tableService->generateQrCode($table);

        return response()->json([
            'message' => 'QR code generated successfully.',
            'data' => $qrCode,
        ]);
    }

    public function getQrCodes(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $qrCodes = $this->tableService->listQrCodes($table);

        return response()->json([
            'message' => 'QR codes retrieved successfully.',
            'data' => $qrCodes,
        ]);
    }
}
