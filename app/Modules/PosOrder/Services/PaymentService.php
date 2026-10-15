<?php

namespace App\Modules\PosOrder\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\CommonStatus;
use App\Core\Enums\MovementType;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Models\InventoryStock;
use App\Core\Enums\OrderSource;
use App\Core\Enums\TableStatus;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\Payment;
use App\Modules\PosOrder\Models\Table;
use App\Modules\RecipeMenu\Models\Recipe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService
{
    /**
     * List payments for an order.
     */
    public function listPaymentsForOrder(string $orderId): Collection
    {
        return Payment::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Process customer payment for an order and deplete inventory based on recipes.
     */
    public function processPayment(Order $order, array $data, ?string $userId = null): Payment
    {
        $storeId = AppContext::storeId() ?: $order->store_id;

        if (AppContext::storeId() && $order->store_id !== AppContext::storeId()) {
            throw new InvalidArgumentException('Order does not belong to the current store.');
        }

        if ($order->status === OrderStatus::CANCELLED) {
            throw new InvalidArgumentException('Cannot process payment for a cancelled order.');
        }

        if ($order->status === OrderStatus::COMPLETED) {
            throw new InvalidArgumentException('Cannot process payment for an already completed order.');
        }

        return DB::transaction(function () use ($order, $data, $storeId, $userId) {
            // Lock order for update
            $lockedOrder = Order::withoutGlobalScope('store')
                ->where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Enforce Decision #7: Only one SUCCESS payment per order
            $existingSuccess = Payment::where('order_id', $lockedOrder->id)
                ->where('status', PaymentStatus::SUCCESS)
                ->exists();

            if ($existingSuccess) {
                throw new InvalidArgumentException('This order has already been paid successfully.');
            }

            $paidAmount = (float) $data['amount'];
            $orderTotal = (float) $lockedOrder->total_amount;

            if ($paidAmount < $orderTotal) {
                throw new InvalidArgumentException("Payment amount ({$paidAmount}) is less than order total ({$orderTotal}).");
            }

            $changeAmount = max(0, round($paidAmount - $orderTotal, 2));
            $paymentNumber = 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $metadata = $data['metadata'] ?? [];
            $metadata['change'] = $changeAmount;

            $method = is_string($data['method']) ? PaymentMethod::from($data['method']) : $data['method'];

            $payment = Payment::create([
                'id' => (string) Str::ulid(),
                'order_id' => $lockedOrder->id,
                'payment_number' => $paymentNumber,
                'method' => $method,
                'provider' => $data['provider'] ?? null,
                'provider_reference' => $data['provider_reference'] ?? null,
                'amount' => $paidAmount,
                'status' => PaymentStatus::SUCCESS,
                'paid_at' => now(),
                'metadata' => $metadata,
            ]);

            // ==========================================
            // RECIPE-BASED STOCK DEPLETION
            // ==========================================
            $lockedOrder->load('orderItems');

            foreach ($lockedOrder->orderItems as $orderItem) {
                // Find active recipe for this product in current store
                $recipe = Recipe::withoutGlobalScope('store')
                    ->where('store_id', $storeId)
                    ->where('product_id', $orderItem->product_id)
                    ->where('status', CommonStatus::ACTIVE)
                    ->with(['recipeItems.ingredient'])
                    ->first();

                if ($recipe) {
                    foreach ($recipe->recipeItems as $recipeItem) {
                        $deductQty = (float) $orderItem->quantity * (float) $recipeItem->quantity;

                        // Lock inventory stock record
                        $stock = InventoryStock::withoutGlobalScope('store')
                            ->where('store_id', $storeId)
                            ->where('ingredient_id', $recipeItem->ingredient_id)
                            ->lockForUpdate()
                            ->first();

                        $currentStockQty = $stock ? (float) $stock->quantity : 0;
                        if (!$stock || $currentStockQty < $deductQty) {
                            $ingredientName = $recipeItem->ingredient ? $recipeItem->ingredient->name : $recipeItem->ingredient_id;
                            throw new InvalidArgumentException("Insufficient stock for ingredient '{$ingredientName}' to fulfill order.");
                        }

                        // Decrement stock
                        $stock->decrement('quantity', $deductQty);

                        // Record immutable inventory movement (negative quantity for sale deduction)
                        InventoryMovement::create([
                            'id' => (string) Str::ulid(),
                            'store_id' => $storeId,
                            'ingredient_id' => $recipeItem->ingredient_id,
                            'unit_id' => $recipeItem->unit_id ?? $recipeItem->ingredient?->unit_id,
                            'type' => MovementType::SALE,
                            'quantity' => -1 * $deductQty,
                            'reference_type' => 'ORDER',
                            'reference_id' => $lockedOrder->id,
                            'notes' => "Sale deduction for Order #{$lockedOrder->order_number}",
                            'created_by' => $userId,
                        ]);
                    }
                }
            }

            $autoComplete = $data['auto_complete'] ?? ($lockedOrder->source === OrderSource::POS);

            if ($autoComplete) {
                // Update order status to COMPLETED
                $lockedOrder->update(['status' => OrderStatus::COMPLETED]);
            } else {
                // For QR orders or active table orders paid upfront:
                // Move status to PREPARING so staff can prepare food/beverages and close order when done
                $lockedOrder->update(['status' => OrderStatus::PREPARING]);
            }

            return $payment->load('order');
        });
    }
}
