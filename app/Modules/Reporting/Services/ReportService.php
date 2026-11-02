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
     * Scope query to store, or tenant if store not set.
     */
    protected function scopeStoreOrTenant($query, string $storeColumn = 'store_id')
    {
        $storeId = AppContext::storeId();
        $tenantId = AppContext::tenantId();

        return $query
            ->when($storeId, fn ($q) => $q->where($storeColumn, $storeId))
            ->when(!$storeId && $tenantId, function ($q) use ($tenantId, $storeColumn) {
                $q->whereIn($storeColumn, function ($sub) use ($tenantId) {
                    $sub->select('id')->from('stores')->where('tenant_id', $tenantId);
                });
            });
    }

    /**
     * Scope query to orders that are completed OR have a successful payment (and are not cancelled).
     */
    protected function scopePaidOrCompletedOrders($query, string $orderTable = 'orders')
    {
        return $query
            ->where("{$orderTable}.status", '!=', OrderStatus::CANCELLED->value)
            ->where(function ($q) use ($orderTable) {
                $q->where("{$orderTable}.status", OrderStatus::COMPLETED->value)
                  ->orWhereExists(function ($sub) use ($orderTable) {
                      $sub->select(DB::raw(1))
                          ->from('payments')
                          ->whereColumn('payments.order_id', "{$orderTable}.id")
                          ->where('payments.status', PaymentStatus::SUCCESS->value);
                  });
            });
    }

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
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $query = DB::table('orders');
        $this->scopeStoreOrTenant($query, 'store_id');
        $this->scopePaidOrCompletedOrders($query, 'orders');

        $summary = $query
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
        $netSales = max(0, $grossSales - $totalDiscounts);
        // Hak Mitra: Penjualan Bersih (Subtotal - Diskon) + Pajak Toko.
        // Biaya Layanan Platform (service_charge) masuk ke Internal Admin (Platform), bukan pendapatan Mitra.
        $totalRevenue = round($netSales + $totalTax, 2);
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
                'tax' => $totalTax,
                'total_tax' => $totalTax,
                'service_charge' => 0.0,
                'total_service' => 0.0,
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
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $query = DB::table('orders');
        $this->scopeStoreOrTenant($query, 'store_id');
        $this->scopePaidOrCompletedOrders($query, 'orders');

        $trend = $query
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("
                TO_CHAR(created_at, 'YYYY-MM-DD') as date,
                COUNT(id) as order_count,
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as discount_amount,
                COALESCE(SUM(subtotal - discount_amount), 0) as net_sales,
                COALESCE(SUM(total_amount - COALESCE(service_charge, 0)), 0) as total_revenue
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
     * Get Top Selling Products with real HPP (COGS) and Gross Margin %.
     */
    public function getTopProducts(?string $startDate = null, ?string $endDate = null, int $limit = 10): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id');
        $this->scopeStoreOrTenant($query, 'o.store_id');
        $this->scopePaidOrCompletedOrders($query, 'o');

        $products = $query
            ->whereBetween('o.created_at', [$start, $end])
            ->selectRaw('
                oi.product_id,
                oi.product_name,
                COALESCE(p.category, \'Menu Utama\') as category,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.subtotal) as total_sales,
                ROUND(AVG(oi.unit_price), 2) as average_price,
                COALESCE(p.cost_price, 0) as unit_cost,
                SUM(oi.quantity * COALESCE(p.cost_price, 0)) as total_cogs
            ')
            ->groupBy('oi.product_id', 'oi.product_name', 'p.category', 'p.cost_price')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        return $products->map(function ($row) {
            $sales = (float) $row->total_sales;
            $cogs = (float) $row->total_cogs;
            $profit = max(0.0, $sales - $cogs);
            $marginPct = $sales > 0 ? round(($profit / $sales) * 100, 1) : 0.0;

            return [
                'id' => $row->product_id,
                'product_id' => $row->product_id,
                'name' => $row->product_name,
                'product_name' => $row->product_name,
                'category' => $row->category ?: 'Menu Utama',
                'quantity_sold' => (float) $row->quantity_sold,
                'total_quantity_sold' => (float) $row->quantity_sold,
                'total_sales' => $sales,
                'revenue' => $sales,
                'average_price' => (float) $row->average_price,
                'unit_cost' => (float) $row->unit_cost,
                'total_cogs' => round($cogs, 2),
                'gross_profit' => round($profit, 2),
                'margin_percent' => $marginPct,
            ];
        })->all();
    }

    /**
     * Get Payment Methods Breakdown.
     */
    public function getPaymentMethodsBreakdown(?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $query = DB::table('payments as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id');
        $this->scopeStoreOrTenant($query, 'o.store_id');

        $methods = $query
            ->where('p.status', PaymentStatus::SUCCESS->value)
            ->whereBetween('p.paid_at', [$start, $end])
            ->selectRaw('
                p.method,
                COUNT(p.id) as payment_count,
                COALESCE(SUM(CASE WHEN p.net_amount > 0 THEN p.net_amount ELSE (p.amount - COALESCE(p.mdr_fee, 0)) END), 0) as total_amount
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
     * Combines theoretical recipe usage from orders and actual usage from inventory_movements.
     */
    public function getInventoryConsumptionReport(?string $startDate = null, ?string $endDate = null): array
    {
        $storeId = AppContext::storeId();
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        // 1. Theoretical usage from paid/completed orders * active recipe items
        $theoQuery = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('recipes as r', function ($join) {
                $join->on('r.product_id', '=', 'oi.product_id')
                     ->on('r.store_id', '=', 'o.store_id')
                     ->where('r.status', '=', 'ACTIVE')
                     ->whereNull('r.deleted_at');
            })
            ->join('recipe_items as ri', 'ri.recipe_id', '=', 'r.id');
        $this->scopeStoreOrTenant($theoQuery, 'o.store_id');
        $this->scopePaidOrCompletedOrders($theoQuery, 'o');

        $theoreticalMap = $theoQuery
            ->whereBetween('o.created_at', [$start, $end])
            ->selectRaw('ri.ingredient_id, SUM(oi.quantity * ri.quantity) as theo_qty')
            ->groupBy('ri.ingredient_id')
            ->pluck('theo_qty', 'ingredient_id');

        // 2. Actual usage from inventory_movements (SALE + WASTE + negative ADJUSTMENT)
        $actualQuery = DB::table('inventory_movements as im')
            ->whereIn('im.type', [MovementType::SALE->value, MovementType::WASTE->value, MovementType::ADJUSTMENT->value])
            ->where('im.quantity', '<', 0)
            ->whereBetween('im.created_at', [$start, $end]);
        $this->scopeStoreOrTenant($actualQuery, 'im.store_id');

        $actualMap = $actualQuery
            ->selectRaw('im.ingredient_id, ABS(SUM(im.quantity)) as actual_qty')
            ->groupBy('im.ingredient_id')
            ->pluck('actual_qty', 'ingredient_id');

        // 3. Load ingredients in store/tenant that have either theoretical/actual usage or active recipes/stock
        $ingQuery = DB::table('ingredients as ing')
            ->join('units as u', 'u.id', '=', 'ing.unit_id')
            ->leftJoin('inventory_stocks as s', function ($join) {
                $join->on('s.ingredient_id', '=', 'ing.id')
                     ->on('s.store_id', '=', 'ing.store_id');
            })
            ->whereNull('ing.deleted_at');
        $this->scopeStoreOrTenant($ingQuery, 'ing.store_id');

        $ingredients = $ingQuery
            ->selectRaw('
                ing.id as ingredient_id,
                ing.name as ingredient_name,
                u.code as unit_code,
                u.name as unit_name,
                COALESCE(s.quantity, 0) as remaining_stock,
                COALESCE(NULLIF(s.average_cost, 0), ing.cost_price, 0) as unit_cost
            ')
            ->get();

        $items = [];
        $totalCogs = 0.0;

        foreach ($ingredients as $ing) {
            $theo = round((float) ($theoreticalMap[$ing->ingredient_id] ?? 0), 2);
            $actual = round((float) ($actualMap[$ing->ingredient_id] ?? 0), 2);

            if ($theo <= 0 && $actual <= 0) {
                continue;
            }

            // If actual movement wasn't recorded for older orders, fallback to theoretical
            $effectiveActual = $actual > 0 ? $actual : $theo;
            $variance = round($effectiveActual - $theo, 2);
            $unitCost = (float) $ing->unit_cost;
            $cost = round($effectiveActual * $unitCost, 2);
            $totalCogs += $cost;

            $status = 'Efisien';
            if ($variance > 0) {
                $status = ($theo > 0 && ($variance / $theo) > 0.1) ? 'Boros' : 'Wajar';
            }

            $items[] = [
                'id' => $ing->ingredient_id,
                'ingredient_id' => $ing->ingredient_id,
                'ingredient_name' => $ing->ingredient_name,
                'name' => $ing->ingredient_name,
                'unit' => $ing->unit_code,
                'unit_code' => $ing->unit_code,
                'unit_name' => $ing->unit_name,
                'theoretical_usage' => $theo,
                'actual_usage' => $effectiveActual,
                'total_consumed_quantity' => $effectiveActual,
                'variance' => $variance,
                'remaining_stock' => (float) $ing->remaining_stock,
                'unit_cost' => $unitCost,
                'cost' => $cost,
                'estimated_cogs' => $cost,
                'status' => $status,
            ];
        }

        usort($items, fn ($a, $b) => $b['estimated_cogs'] <=> $a['estimated_cogs']);

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
                'total_cogs' => round($totalCogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_profit_margin_percentage' => $grossMargin,
            ],
            'items' => $items,
        ];
    }

    /**
     * Get Cashier Shift & Cash Audit Report.
     */
    public function getCashierShiftReport(?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $query = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->leftJoin('payments as p', function ($join) {
                $join->on('p.order_id', '=', 'o.id')
                    ->where('p.status', '=', PaymentStatus::SUCCESS->value);
            });
        $this->scopeStoreOrTenant($query, 'o.store_id');
        $this->scopePaidOrCompletedOrders($query, 'o');

        $cashiers = $query
            ->whereBetween('o.created_at', [$start, $end])
            ->selectRaw("
                o.created_by as user_id,
                COALESCE(u.name, 'Customer (QR Self-Order)') as user_name,
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(o.total_amount - COALESCE(o.service_charge, 0)), 0) as total_collected,
                COALESCE(SUM(CASE WHEN p.method = 'CASH' THEN (CASE WHEN p.net_amount > 0 THEN p.net_amount ELSE p.amount - COALESCE(p.mdr_fee, 0) END) ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN p.method = 'QRIS' THEN (CASE WHEN p.net_amount > 0 THEN p.net_amount ELSE p.amount - COALESCE(p.mdr_fee, 0) END) ELSE 0 END), 0) as qris_total,
                COALESCE(SUM(CASE WHEN p.method NOT IN ('CASH', 'QRIS') AND p.method IS NOT NULL THEN (CASE WHEN p.net_amount > 0 THEN p.net_amount ELSE p.amount - COALESCE(p.mdr_fee, 0) END) ELSE 0 END), 0) as other_total
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
     * Get Channel Distribution (Dine In, Takeaway, POS, QR, Delivery).
     */
    public function getChannelDistribution(?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $query = DB::table('orders');
        $this->scopeStoreOrTenant($query, 'store_id');
        $this->scopePaidOrCompletedOrders($query, 'orders');
        $channels = $query
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("
                COALESCE(channel, source) as channel_name,
                COUNT(id) as count,
                COALESCE(SUM(total_amount - COALESCE(service_charge, 0)), 0) as total_amount
            ")
            ->groupBy('channel_name')
            ->orderByDesc('count')
            ->get();

        $totalCount = $channels->sum('count');

        return $channels->map(function ($row) use ($totalCount) {
            $raw = strtoupper($row->channel_name ?? 'POS');
            $name = match ($raw) {
                'DINE_IN' => 'Dine In',
                'TAKEAWAY' => 'Takeaway',
                'POS' => 'Kasir (POS)',
                'QR' => 'QR Order',
                'DELIVERY' => 'Delivery',
                default => $row->channel_name,
            };
            $color = match ($raw) {
                'DINE_IN' => '#F59E0B',
                'TAKEAWAY' => '#10B981',
                'POS' => '#8B5CF6',
                'QR' => '#2563EB',
                'DELIVERY' => '#EC4899',
                default => '#6B7280',
            };

            return [
                'name' => $name,
                'count' => (int) $row->count,
                'total_amount' => (float) $row->total_amount,
                'percentage' => $totalCount > 0 ? round(($row->count / $totalCount) * 100, 1) : 0,
                'color' => $color,
            ];
        })->all();
    }

    /**
     * Get Low Stock Ingredients.
     */
    public function getLowStocks(int $limit = 5): array
    {
        $query = DB::table('inventory_stocks as s')
            ->join('ingredients as ing', 'ing.id', '=', 's.ingredient_id')
            ->join('units as u', 'u.id', '=', 'ing.unit_id');
        $this->scopeStoreOrTenant($query, 's.store_id');

        $stocks = $query
            ->selectRaw('
                ing.id,
                ing.name,
                s.quantity as remaining,
                ing.min_stock,
                u.code as unit_code,
                u.name as unit_name,
                CASE
                    WHEN s.quantity <= 0 THEN \'Habis\'
                    WHEN s.quantity <= ing.min_stock THEN \'Sangat Rendah\'
                    WHEN s.quantity <= (ing.min_stock * 2) THEN \'Menipis\'
                    ELSE \'Aman\'
                END as status,
                CASE
                    WHEN s.quantity <= ing.min_stock THEN true
                    ELSE false
                END as is_critical
            ')
            ->orderByRaw('CASE WHEN ing.min_stock > 0 THEN s.quantity / ing.min_stock ELSE s.quantity END ASC')
            ->limit($limit)
            ->get();

        return $stocks->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'remaining' => (float) $row->remaining,
            'unit_code' => $row->unit_code,
            'unit_name' => $row->unit_name,
            'min_stock' => (float) $row->min_stock,
            'status' => $row->status,
            'is_critical' => (bool) $row->is_critical,
        ])->all();
    }

    /**
     * Unified Dashboard Payload.
     */
    public function getDashboardOverview(?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $sales = $this->getSalesSummary($startDate, $endDate);
        $trend = $this->getSalesTrend($startDate, $endDate);
        $topProducts = $this->getTopProducts($startDate, $endDate, 5);
        $payments = $this->getPaymentMethodsBreakdown($startDate, $endDate);
        $channels = $this->getChannelDistribution($startDate, $endDate);
        $lowStocks = $this->getLowStocks(5);

        // Calculate comparison with previous period
        $durationDays = max(1, $start->diffInDays($end) + 1);
        $prevStart = $start->copy()->subDays($durationDays);
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevSales = $this->getSalesSummary($prevStart->toDateString(), $prevEnd->toDateString());

        $currentRevenue = $sales['kpis']['total_revenue'];
        $prevRevenue = $prevSales['kpis']['total_revenue'];
        $salesGrowth = $prevRevenue > 0
            ? round((($currentRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : ($currentRevenue > 0 ? 100.0 : 0.0);

        $currentOrders = $sales['kpis']['total_orders'];
        $prevOrders = $prevSales['kpis']['total_orders'];
        $ordersGrowth = $prevOrders > 0
            ? round((($currentOrders - $prevOrders) / $prevOrders) * 100, 1)
            : ($currentOrders > 0 ? 100.0 : 0.0);

        $currentAov = $sales['kpis']['average_order_value'];
        $prevAov = $prevSales['kpis']['average_order_value'];
        $aovGrowth = $prevAov > 0
            ? round((($currentAov - $prevAov) / $prevAov) * 100, 1)
            : 0.0;

        // Calculate actual COGS from sold products' cost_price (HPP)
        $cogsQuery = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id');
        $this->scopeStoreOrTenant($cogsQuery, 'o.store_id');
        $this->scopePaidOrCompletedOrders($cogsQuery, 'o');
        $actualCogs = (float) $cogsQuery
            ->whereBetween('o.created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(oi.quantity * COALESCE(p.cost_price, 0)), 0) as total_cogs')
            ->value('total_cogs');

        $profitEstimate = max(0, $sales['kpis']['net_sales'] - $actualCogs);

        // Today orders breakdown by status
        $todayQuery = DB::table('orders');
        $this->scopeStoreOrTenant($todayQuery, 'store_id');
        $todayOrders = $todayQuery
            ->whereDate('created_at', Carbon::today())
            ->selectRaw('
                status,
                COUNT(id) as count
            ')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $todayNew = (int) ($todayOrders[OrderStatus::PENDING->value] ?? 0);
        $todayProcessing = (int) (($todayOrders[OrderStatus::CONFIRMED->value] ?? 0) + ($todayOrders[OrderStatus::PREPARING->value] ?? 0));
        $todayReady = (int) ($todayOrders[OrderStatus::READY->value] ?? 0);
        $todayCompleted = (int) ($todayOrders[OrderStatus::COMPLETED->value] ?? 0);
        $todayCancelled = (int) ($todayOrders[OrderStatus::CANCELLED->value] ?? 0);
        $todayTotal = $todayNew + $todayProcessing + $todayReady + $todayCompleted + $todayCancelled;

        return [
            'period' => $sales['period'],
            'kpis' => array_merge($sales['kpis'], [
                'estimated_profit' => $profitEstimate,
                'sales_growth' => $salesGrowth,
                'orders_growth' => $ordersGrowth,
                'aov_growth' => $aovGrowth,
                'profit_growth' => $salesGrowth,
            ]),
            'sales_trend' => $trend,
            'top_products' => $topProducts,
            'payment_methods' => $payments,
            'channels' => $channels,
            'low_stocks' => $lowStocks,
            'today_orders' => [
                'total' => $todayTotal,
                'new' => $todayNew,
                'processing' => $todayProcessing,
                'ready' => $todayReady,
                'completed' => $todayCompleted,
                'cancelled' => $todayCancelled,
            ],
        ];
    }
}
