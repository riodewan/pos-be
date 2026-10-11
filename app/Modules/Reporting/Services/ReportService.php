<?php

namespace App\Modules\Reporting\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\MovementType;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Parse date range filters (default to current month if null).
     */
    protected function parseDateRange(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate
            ? Carbon::parse($startDate)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $end = $endDate
            ? Carbon::parse($endDate)->endOfDay()
            : Carbon::now()->endOfDay();

        return [$start, $end];
    }

    /**
     * Get Sales Summary and KPIs.
     */
    public function getSalesSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $storeId = AppContext::storeId();
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $summary = DB::table('orders')
            ->where('store_id', $storeId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('
                COUNT(id) as total_orders,
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as total_discounts,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COALESCE(SUM(service_charge), 0) as total_service,
                COALESCE(SUM(total_amount), 0) as total_revenue
            ')
            ->first();

        $totalOrders = (int) ($summary->total_orders ?? 0);
        $grossSales = (float) ($summary->gross_sales ?? 0);
        $totalDiscounts = (float) ($summary->total_discounts ?? 0);
        $totalTax = (float) ($summary->total_tax ?? 0);
        $totalService = (float) ($summary->total_service ?? 0);
        $totalRevenue = (float) ($summary->total_revenue ?? 0);
        $netSales = max(0, $grossSales - $totalDiscounts);
        $aov = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0;

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'kpis' => [
                'total_orders' => $totalOrders,
                'gross_sales' => $grossSales,
                'total_discounts' => $totalDiscounts,
                'net_sales' => $netSales,
                'total_tax' => $totalTax,
                'total_service' => $totalService,
                'total_revenue' => $totalRevenue,
                'average_order_value' => $aov,
            ],
        ];
    }

    /**
     * Get Daily Sales Trend.
     */
    public function getSalesTrend(?string $startDate = null, ?string $endDate = null): array
    {
        $storeId = AppContext::storeId();
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $trend = DB::table('orders')
            ->where('store_id', $storeId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("
                TO_CHAR(created_at, 'YYYY-MM-DD') as date,
                COUNT(id) as order_count,
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as discount_amount,
                COALESCE(SUM(subtotal - discount_amount), 0) as net_sales,
                COALESCE(SUM(total_amount), 0) as total_revenue
            ")
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
            ->orderBy('date', 'asc')
            ->get();

        return $trend->map(fn ($row) => [
            'date' => $row->date,
            'order_count' => (int) $row->order_count,
            'gross_sales' => (float) $row->gross_sales,
            'discount_amount' => (float) $row->discount_amount,
            'net_sales' => (float) $row->net_sales,
            'total_revenue' => (float) $row->total_revenue,
        ])->all();
    }

    /**
     * Get Top Selling Products.
     */
    public function getTopProducts(?string $startDate = null, ?string $endDate = null, int $limit = 10): array
    {
        $storeId = AppContext::storeId();
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $products = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.store_id', $storeId)
            ->where('o.status', OrderStatus::COMPLETED->value)
            ->whereBetween('o.created_at', [$start, $end])
            ->selectRaw('
                oi.product_id,
                oi.product_name,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.subtotal) as total_sales,
                ROUND(AVG(oi.unit_price), 2) as average_price
            ')
            ->groupBy('oi.product_id', 'oi.product_name')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        return $products->map(fn ($row) => [
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'quantity_sold' => (float) $row->quantity_sold,
            'total_sales' => (float) $row->total_sales,
            'average_price' => (float) $row->average_price,
        ])->all();
    }

    /**
     * Get Payment Methods Breakdown.
     */
    public function getPaymentMethodsBreakdown(?string $startDate = null, ?string $endDate = null): array
    {
        $storeId = AppContext::storeId();
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $methods = DB::table('payments as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('o.store_id', $storeId)
            ->where('p.status', PaymentStatus::SUCCESS->value)
            ->whereBetween('p.paid_at', [$start, $end])
            ->selectRaw('
                p.method,
                COUNT(p.id) as payment_count,
                COALESCE(SUM(p.amount), 0) as total_amount
            ')
            ->groupBy('p.method')
            ->orderByDesc('total_amount')
            ->get();

        $overallTotal = $methods->sum('total_amount');

        return $methods->map(fn ($row) => [
            'method' => $row->method,
            'payment_count' => (int) $row->payment_count,
            'total_amount' => (float) $row->total_amount,
            'percentage' => $overallTotal > 0 ? round(((float) $row->total_amount / $overallTotal) * 100, 2) : 0.0,
        ])->all();
    }

    /**
     * Get Raw Material Consumption & HPP (COGS) Report.
     */
    public function getInventoryConsumptionReport(?string $startDate = null, ?string $endDate = null): array
    {
        $storeId = AppContext::storeId();
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $consumption = DB::table('inventory_movements as im')
            ->join('ingredients as ing', 'ing.id', '=', 'im.ingredient_id')
            ->join('units as u', 'u.id', '=', 'im.unit_id')
            ->leftJoin('inventory_stocks as s', function ($join) use ($storeId) {
                $join->on('s.ingredient_id', '=', 'im.ingredient_id')
                    ->where('s.store_id', '=', $storeId);
            })
            ->where('im.store_id', $storeId)
            ->where('im.type', MovementType::SALE->value)
            ->whereBetween('im.created_at', [$start, $end])
            ->selectRaw('
                im.ingredient_id,
                ing.name as ingredient_name,
                u.code as unit_code,
                u.name as unit_name,
                ABS(SUM(im.quantity)) as total_consumed_quantity,
                COALESCE(s.average_cost, 0) as unit_cost,
                ROUND(ABS(SUM(im.quantity)) * COALESCE(s.average_cost, 0), 2) as estimated_cogs
            ')
            ->groupBy('im.ingredient_id', 'ing.name', 'u.code', 'u.name', 's.average_cost')
            ->orderByDesc('total_consumed_quantity')
            ->get();

        $totalCogs = $consumption->sum('estimated_cogs');

        // Net sales in same period to calculate gross profit margin
        $salesSummary = $this->getSalesSummary($start->toDateString(), $end->toDateString());
        $netSales = $salesSummary['kpis']['net_sales'];
        $grossProfit = max(0, $netSales - $totalCogs);
        $grossMargin = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'financial_analysis' => [
                'net_sales' => $netSales,
                'total_cogs' => (float) $totalCogs,
                'gross_profit' => (float) $grossProfit,
                'gross_profit_margin_percentage' => $grossMargin,
            ],
            'items' => $consumption->map(fn ($row) => [
                'ingredient_id' => $row->ingredient_id,
                'ingredient_name' => $row->ingredient_name,
                'unit_code' => $row->unit_code,
                'unit_name' => $row->unit_name,
                'total_consumed_quantity' => (float) $row->total_consumed_quantity,
                'unit_cost' => (float) $row->unit_cost,
                'estimated_cogs' => (float) $row->estimated_cogs,
            ])->all(),
        ];
    }

    /**
     * Get Cashier Shift & Cash Audit Report.
     */
    public function getCashierShiftReport(?string $startDate = null, ?string $endDate = null): array
    {
        $storeId = AppContext::storeId();
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $cashiers = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->leftJoin('payments as p', function ($join) {
                $join->on('p.order_id', '=', 'o.id')
                    ->where('p.status', '=', PaymentStatus::SUCCESS->value);
            })
            ->where('o.store_id', $storeId)
            ->where('o.status', OrderStatus::COMPLETED->value)
            ->whereBetween('o.created_at', [$start, $end])
            ->selectRaw("
                o.created_by as user_id,
                COALESCE(u.name, 'Customer (QR Self-Order)') as user_name,
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(o.total_amount), 0) as total_collected,
                COALESCE(SUM(CASE WHEN p.method = 'CASH' THEN p.amount - COALESCE((p.metadata->>'change')::numeric, 0) ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN p.method = 'QRIS' THEN p.amount ELSE 0 END), 0) as qris_total,
                COALESCE(SUM(CASE WHEN p.method NOT IN ('CASH', 'QRIS') AND p.method IS NOT NULL THEN p.amount ELSE 0 END), 0) as other_total
            ")
            ->groupBy('o.created_by', 'u.name')
            ->orderByDesc('total_collected')
            ->get();

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'cashiers' => $cashiers->map(fn ($row) => [
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'total_orders' => (int) $row->total_orders,
                'total_collected' => (float) $row->total_collected,
                'cash_total' => (float) $row->cash_total,
                'qris_total' => (float) $row->qris_total,
                'other_total' => (float) $row->other_total,
            ])->all(),
        ];
    }

    /**
     * Unified Dashboard Payload.
     */
    public function getDashboardOverview(?string $startDate = null, ?string $endDate = null): array
    {
        $sales = $this->getSalesSummary($startDate, $endDate);
        $trend = $this->getSalesTrend($startDate, $endDate);
        $topProducts = $this->getTopProducts($startDate, $endDate, 5);
        $payments = $this->getPaymentMethodsBreakdown($startDate, $endDate);

        return [
            'period' => $sales['period'],
            'kpis' => $sales['kpis'],
            'sales_trend' => $trend,
            'top_products' => $topProducts,
            'payment_methods' => $payments,
        ];
    }
}
