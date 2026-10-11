<?php

namespace App\Modules\System\Http\Controllers;

use App\Modules\System\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List audit logs with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $logs = $this->auditService->listLogs(
            action: $request->query('action'),
            userId: $request->query('user_id'),
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date'),
            perPage: (int) $request->query('per_page', 20)
        );

        return response()->json([
            'message' => 'Audit logs retrieved successfully.',
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
