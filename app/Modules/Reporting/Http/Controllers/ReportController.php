<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Unified Dashboard Overview.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->reportService->getDashboardOverview($startDate, $endDate);

        return response()->json([
            'message' => 'Dashboard overview retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Sales Summary & Trend Report.
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $summary = $this->reportService->getSalesSummary($startDate, $endDate);
        $trend = $this->reportService->getSalesTrend($startDate, $endDate);

        return response()->json([
            'message' => 'Sales report retrieved successfully.',
            'data' => [
                'period' => $summary['period'],
                'kpis' => $summary['kpis'],
                'trend' => $trend,
            ],
        ]);
    }

    /**
     * Top Selling Products Report.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $limit = (int) $request->query('limit', 10);

        $products = $this->reportService->getTopProducts($startDate, $endDate, $limit);

        return response()->json([
            'message' => 'Top products report retrieved successfully.',
            'data' => $products,
        ]);
    }

    /**
     * Payment Methods Breakdown.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $breakdown = $this->reportService->getPaymentMethodsBreakdown($startDate, $endDate);

        return response()->json([
            'message' => 'Payment methods breakdown retrieved successfully.',
            'data' => $breakdown,
        ]);
    }

    /**
     * Raw Material Inventory Usage & COGS / HPP Report.
     */
    public function inventoryUsage(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $report = $this->reportService->getInventoryConsumptionReport($startDate, $endDate);

        return response()->json([
            'message' => 'Inventory consumption report retrieved successfully.',
            'data' => $report,
        ]);
    }

    /**
     * Cashier Shift & Cash Audit Report.
     */
    public function cashierShift(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $report = $this->reportService->getCashierShiftReport($startDate, $endDate);

        return response()->json([
            'message' => 'Cashier shift report retrieved successfully.',
            'data' => $report,
        ]);
    }
}
