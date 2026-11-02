<?php

namespace App\Modules\PosOrder\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\OrderSource;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\QrStatus;
use App\Core\Enums\TableStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\OrderItem;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Models\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderService
{
    /**
     * List orders for the current store with filtering and pagination.
     */
    public function listOrders(
        ?string $status = null,
        ?string $tableId = null,
        ?string $source = null,
        ?string $channel = null,
        ?string $search = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return Order::with(['table', 'orderItems.product', 'payments', 'createdBy'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($tableId, fn ($q) => $q->where('table_id', $tableId))
            ->when($source, fn ($q) => $q->where('source', $source))
            ->when($channel, fn ($q) => $q->where('channel', $channel))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('order_number', 'ILIKE', "%{$search}%")
                        ->orWhere('customer_name', 'ILIKE', "%{$search}%")
                        ->orWhere('customer_phone', 'ILIKE', "%{$search}%")
                        ->orWhereHas('table', fn ($t) => $t->where('name', 'ILIKE', "%{$search}%"));
                });
            })
            ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get order details.
     */
    public function getOrder(string $id): Order
    {
        return Order::with(['table', 'orderItems.product', 'payments', 'createdBy'])
            ->findOrFail($id);
    }

    /**
     * Create POS order (Cashier / Staff).
     */
    public function createPosOrder(array $data, ?string $userId = null): Order
    {
        $storeId = AppContext::storeId();

        // Cross-store validation: Table (if provided) must belong to current store and be ACTIVE
        if (!empty($data['table_id'])) {
            $table = Table::withoutGlobalScope('store')
                ->where('id', $data['table_id'])
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->first();

            if (!$table) {
                throw new InvalidArgumentException('The selected table does not belong to the current store.');
            }

            if ($table->status !== TableStatus::ACTIVE) {
                throw new InvalidArgumentException('The selected table is currently inactive or unavailable.');
            }
        }

        // Cross-store validation: All products must belong to current store and be ACTIVE
        $productIds = collect($data['items'])->pluck('product_id')->unique()->all();
        $products = Product::withoutGlobalScope('store')
            ->whereIn('id', $productIds)
            ->where('store_id', $storeId)
            ->where('status', CommonStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($productIds)) {
            throw new InvalidArgumentException('One or more selected products are invalid, inactive, or do not belong to the current store.');
        }

        return DB::transaction(function () use ($data, $storeId, $userId, $products) {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (float) $item['quantity'];
                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : (float) ($product->selling_price ?? $product->price ?? 0);
                $itemDiscount = isset($item['discount_amount']) ? (float) $item['discount_amount'] : 0;
                $itemSubtotal = max(0, ($quantity * $unitPrice) - $itemDiscount);

                $subtotal += $itemSubtotal;

                $itemsToCreate[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $itemDiscount,
                    'subtotal' => $itemSubtotal,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $orderDiscount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : 0;
            $taxAmount = isset($data['tax_amount']) ? (float) $data['tax_amount'] : 0;
            $serviceCharge = isset($data['service_charge']) ? (float) $data['service_charge'] : 0;
            $totalAmount = max(0, $subtotal - $orderDiscount + $taxAmount + $serviceCharge);

            // Auto-resolve or create Customer based on phone equality & name similarity
            $tenantId = AppContext::tenantId();
            if (!$tenantId && $storeId) {
                $store = \App\Modules\Tenant\Models\Store::find($storeId);
                $tenantId = $store?->tenant_id;
            }
            $customer = null;
            if ($tenantId) {
                $customerSyncService = app(\App\Modules\Customer\Services\CustomerSyncService::class);
                $customer = $customerSyncService->resolveOrCreateFromOrderData(
                    $tenantId,
                    $data['customer_name'] ?? null,
                    $data['customer_phone'] ?? null
                );
            }

            $order = Order::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'table_id' => $data['table_id'] ?? null,
                'customer_id' => $customer?->id,
                'order_number' => $orderNumber,
                'source' => $data['source'] ?? OrderSource::POS,
                'channel' => $data['channel'] ?? 'DINE_IN',
                'customer_name' => $customer?->name ?? ($data['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? ($data['customer_phone'] ?? null),
                'status' => OrderStatus::PENDING,
                'subtotal' => $subtotal,
                'discount_amount' => $orderDiscount,
                'tax_amount' => $taxAmount,
                'service_charge' => $serviceCharge,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($itemsToCreate as $itemData) {
                OrderItem::create([
                    'id' => (string) Str::ulid(),
                    'order_id' => $order->id,
                    ...$itemData,
                ]);
            }

            if ($customer) {
                app(\App\Modules\Customer\Services\CustomerSyncService::class)->recalculateCustomerStats($customer);
            }

            // Create in-app operational notification
            try {
                \App\Modules\System\Models\Notification::create([
                    'tenant_id' => AppContext::tenantId(),
                    'store_id' => $storeId,
                    'title' => "Pesanan Kasir #{$order->order_number}",
                    'subtitle' => "Pesanan {$order->channel} senilai Rp " . number_format($order->total_amount, 0, ',', '.') . " berhasil dibuat.",
                    'category' => 'Pesanan',
                    'is_read' => false,
                    'target_url' => '/dashboard/orders',
                ]);
            } catch (\Throwable $e) {}

            return $order->load(['table', 'orderItems.product']);
        });
    }

    /**
     * Create Customer QR Order.
     * Public endpoint: customer orders via scanned QR token.
     */
    public function createQrOrder(string $qrToken, array $data): Order
    {
        $qrCode = QrCode::withoutGlobalScope('store')
            ->where('token', $qrToken)
            ->where('status', QrStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->with(['table', 'store'])
            ->first();

        if (!$qrCode || !$qrCode->table || !$qrCode->store) {
            throw new InvalidArgumentException('Invalid or inactive QR code.');
        }

        if ($qrCode->table->status !== TableStatus::ACTIVE) {
            throw new InvalidArgumentException('Meja saat ini sedang tidak aktif atau tidak tersedia.');
        }

        if ($qrCode->store->status !== CommonStatus::ACTIVE) {
            throw new InvalidArgumentException('Outlet/Toko saat ini sedang tidak beroperasi.');
        }

        $storeId = $qrCode->store_id;
        $tableId = $qrCode->table_id;

        // Verify products belong to QR's store
        $productIds = collect($data['items'])->pluck('product_id')->unique()->all();
        $products = Product::withoutGlobalScope('store')
            ->whereIn('id', $productIds)
            ->where('store_id', $storeId)
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($productIds)) {
            throw new InvalidArgumentException('One or more selected products are invalid or unavailable.');
        }

        return DB::transaction(function () use ($data, $storeId, $tableId, $products) {
            $orderNumber = 'QR-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) ($product->selling_price ?? $product->price ?? 0);
                $itemSubtotal = $quantity * $unitPrice;

                $subtotal += $itemSubtotal;

                $itemsToCreate[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0,
                    'subtotal' => $itemSubtotal,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $store = \App\Modules\Tenant\Models\Store::find($storeId);
            $tenantId = $store ? $store->tenant_id : null;
            $settingService = app(\App\Modules\System\Services\SettingService::class);
            $taxPct = (float) $settingService->get('tax_percentage', $storeId, $tenantId, 0);
            if ($taxPct <= 0) {
                $activeTaxes = \App\Modules\Tenant\Models\StoreTaxFee::withoutGlobalScope('store')
                    ->where('store_id', $storeId)
                    ->where('type', 'Pajak')
                    ->where('is_active', true)
                    ->get();
                foreach ($activeTaxes as $t) {
                    if ($t->value_type === 'percent') {
                        $taxPct += (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $t->value));
                    }
                }
            }
            $platformFeePct = (float) $settingService->get(
                'platform_fee_percentage',
                null,
                $tenantId,
                $settingService->get('service_charge_percentage', $storeId, $tenantId, 0)
            );

            $serviceCharge = $platformFeePct > 0 ? round(($subtotal * $platformFeePct) / 100) : 0;
            $taxAmount = $taxPct > 0 ? round(($subtotal * $taxPct) / 100) : 0;
            $totalAmount = $subtotal + $serviceCharge + $taxAmount;

            // Auto-resolve or create Customer from QR Self-Order
            $customer = null;
            if ($tenantId) {
                $customerSyncService = app(\App\Modules\Customer\Services\CustomerSyncService::class);
                $customer = $customerSyncService->resolveOrCreateFromOrderData(
                    $tenantId,
                    $data['customer_name'] ?? null,
                    $data['customer_phone'] ?? null
                );
            }

            $order = Order::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'table_id' => $tableId,
                'customer_id' => $customer?->id,
                'order_number' => $orderNumber,
                'source' => OrderSource::QR,
                'customer_name' => $customer?->name ?? ($data['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? ($data['customer_phone'] ?? null),
                'status' => OrderStatus::PENDING,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_amount' => $taxAmount,
                'service_charge' => $serviceCharge,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => null,
            ]);

            foreach ($itemsToCreate as $itemData) {
                OrderItem::create([
                    'id' => (string) Str::ulid(),
                    'order_id' => $order->id,
                    ...$itemData,
                ]);
            }

            if ($customer) {
                app(\App\Modules\Customer\Services\CustomerSyncService::class)->recalculateCustomerStats($customer);
            }

            // Direct Customer Payment (Self-Service QR Flow)
            if (!empty($data['payment_method'])) {
                $method = PaymentMethod::tryFrom($data['payment_method']) ?? PaymentMethod::QRIS;
                $paymentService = app(\App\Modules\PosOrder\Services\PaymentService::class);
                $paymentService->processPayment($order, [
                    'method' => $method,
                    'amount' => $totalAmount,
                    'provider' => 'QRIS_SIMULATOR',
                    'provider_reference' => 'QRIS-' . date('Ymd') . '-' . strtoupper(Str::random(8)),
                    'metadata' => [
                        'channel' => 'CUSTOMER_QR_SELF_SERVICE',
                        'paid_at' => now()->toIso8601String(),
                    ],
                    'auto_complete' => false,
                ]);
            }

            // Create in-app operational notification for QR Order
            try {
                $tableName = $qrCode->table ? "Meja " . ($qrCode->table->table_number ?? $qrCode->table->name ?? '01') : 'Meja Pelanggan';
                \App\Modules\System\Models\Notification::create([
                    'tenant_id' => $tenantId,
                    'store_id' => $storeId,
                    'title' => "Pesanan QR Masuk #{$order->order_number}",
                    'subtitle' => "{$tableName} telah mengirimkan pesanan mandiri senilai Rp " . number_format($order->total_amount, 0, ',', '.') . ".",
                    'category' => 'Pesanan',
                    'is_read' => false,
                    'target_url' => '/dashboard/orders',
                ]);
            } catch (\Throwable $e) {}

            return $order->fresh(['table', 'orderItems.product', 'payments']);
        });
    }

    /**
     * Transition order status.
     */
    public function updateStatus(Order $order, OrderStatus|string $newStatus): Order
    {
        if (is_string($newStatus)) {
            $newStatus = OrderStatus::from($newStatus);
        }

        if ($order->status === OrderStatus::COMPLETED) {
            throw new InvalidArgumentException('Cannot modify status of an already COMPLETED order.');
        }

        if ($order->status === OrderStatus::CANCELLED) {
            throw new InvalidArgumentException('Cannot modify status of a CANCELLED order.');
        }

        // If transitioning to CANCELLED, verify no successful payment exists
        if ($newStatus === OrderStatus::CANCELLED) {
            $hasSuccessPayment = $order->payments()->where('status', PaymentStatus::SUCCESS)->exists();
            if ($hasSuccessPayment) {
                throw new InvalidArgumentException('Cannot cancel an order that has already been paid.');
            }
        }

        $order->update(['status' => $newStatus]);

        return $order->fresh(['table', 'orderItems.product', 'payments']);
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(Order $order, ?string $reason = null): Order
    {
        if ($order->status === OrderStatus::COMPLETED) {
            throw new InvalidArgumentException('Cannot cancel an already COMPLETED order.');
        }

        if ($order->status === OrderStatus::CANCELLED) {
            throw new InvalidArgumentException('Order is already cancelled.');
        }

        $hasSuccessPayment = $order->payments()->where('status', PaymentStatus::SUCCESS)->exists();
        if ($hasSuccessPayment) {
            throw new InvalidArgumentException('Cannot cancel an order that has already been paid.');
        }

        $order->update([
            'status' => OrderStatus::CANCELLED,
            'notes' => $reason ? ($order->notes ? $order->notes . "\n[Cancelled: {$reason}]" : "[Cancelled: {$reason}]") : $order->notes,
        ]);

        return $order->fresh(['table', 'orderItems.product', 'payments']);
    }
}
