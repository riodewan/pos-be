<?php

namespace App\Modules\PosOrder\Services;

use App\Core\Context\AppContext;
use App\Core\Enums\OrderStatus;
use App\Core\Enums\QrStatus;
use App\Core\Enums\TableStatus;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Models\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TableService
{
    /**
     * List all tables for current store with operational occupancy state.
     */
    public function listTables(): Collection
    {
        $storeId = AppContext::storeId();

        $tables = Table::with(['activeQrCode'])
            ->orderBy('code', 'asc')
            ->get();

        // Compute operational occupancy based on active orders
        $activeOrdersByTable = Order::withoutGlobalScope('store')
            ->where('store_id', $storeId)
            ->whereNotNull('table_id')
            ->whereIn('status', [
                OrderStatus::PENDING->value,
                OrderStatus::CONFIRMED->value,
                OrderStatus::PREPARING->value,
                OrderStatus::READY->value,
            ])
            ->get()
            ->groupBy('table_id');

        return $tables->map(function (Table $table) use ($activeOrdersByTable) {
            $hasActiveOrders = $activeOrdersByTable->has($table->id);
            $activeOrders = $hasActiveOrders ? $activeOrdersByTable->get($table->id) : collect();

            $table->is_occupied = $hasActiveOrders;
            $table->active_orders_count = $activeOrders->count();
            $table->current_order_id = $hasActiveOrders ? $activeOrders->first()->id : null;

            if ($hasActiveOrders) {
                $table->operational_status = 'Terisi';
            } elseif (!$table->operational_status || $table->operational_status === 'Terisi') {
                $table->operational_status = 'Kosong';
            }

            return $table;
        });
    }

    /**
     * Get a specific table with active orders and active QR code.
     */
    public function getTable(string $id): Table
    {
        $table = Table::with(['activeQrCode'])->findOrFail($id);

        $activeOrders = Order::withoutGlobalScope('store')
            ->where('store_id', $table->store_id)
            ->where('table_id', $table->id)
            ->whereIn('status', [
                OrderStatus::PENDING->value,
                OrderStatus::CONFIRMED->value,
                OrderStatus::PREPARING->value,
                OrderStatus::READY->value,
            ])
            ->get();

        $table->is_occupied = $activeOrders->isNotEmpty();
        $table->active_orders_count = $activeOrders->count();
        $table->current_order_id = $activeOrders->isNotEmpty() ? $activeOrders->first()->id : null;

        return $table;
    }

    /**
     * Create a new table in the current store.
     */
    public function createTable(array $data): Table
    {
        $storeId = AppContext::storeId();

        // Verify unique table code for store
        $exists = Table::withoutGlobalScope('store')
            ->where('store_id', $storeId)
            ->where('code', $data['code'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("Table code '{$data['code']}' already exists in this store.");
        }

        return DB::transaction(function () use ($data, $storeId) {
            $table = Table::create([
                'id' => (string) Str::ulid(),
                'store_id' => $storeId,
                'name' => $data['name'],
                'code' => $data['code'],
                'capacity' => $data['capacity'] ?? null,
                'status' => $data['status'] ?? TableStatus::ACTIVE,
                'area' => $data['area'] ?? 'Indoor',
                'category' => $data['category'] ?? 'Meja Standar (2-4 Orang)',
                'table_number' => $data['table_number'] ?? null,
                'operational_status' => $data['operational_status'] ?? 'Kosong',
                'notes' => $data['notes'] ?? null,
            ]);

            // Auto-generate initial QR code
            $this->generateQrCode($table);

            return $table->load('activeQrCode');
        });
    }

    /**
     * Update an existing table.
     */
    public function updateTable(Table $table, array $data): Table
    {
        if (isset($data['code']) && $data['code'] !== $table->code) {
            $exists = Table::withoutGlobalScope('store')
                ->where('store_id', $table->store_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $table->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException("Table code '{$data['code']}' already exists in this store.");
            }
        }

        $table->update([
            'name' => $data['name'] ?? $table->name,
            'code' => $data['code'] ?? $table->code,
            'capacity' => array_key_exists('capacity', $data) ? $data['capacity'] : $table->capacity,
            'status' => $data['status'] ?? $table->status,
            'area' => $data['area'] ?? $table->area,
            'category' => $data['category'] ?? $table->category,
            'table_number' => array_key_exists('table_number', $data) ? $data['table_number'] : $table->table_number,
            'operational_status' => $data['operational_status'] ?? $table->operational_status,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $table->notes,
        ]);

        return $table->fresh(['activeQrCode']);
    }

    /**
     * Delete a table.
     */
    public function deleteTable(Table $table): void
    {
        // Prevent deleting if table has active orders
        $hasActiveOrders = Order::withoutGlobalScope('store')
            ->where('store_id', $table->store_id)
            ->where('table_id', $table->id)
            ->whereIn('status', [
                OrderStatus::PENDING->value,
                OrderStatus::CONFIRMED->value,
                OrderStatus::PREPARING->value,
                OrderStatus::READY->value,
            ])
            ->exists();

        if ($hasActiveOrders) {
            throw new InvalidArgumentException('Cannot delete table with active pending or in-progress orders.');
        }

        // Deactivate active QR code
        QrCode::withoutGlobalScope('store')
            ->where('table_id', $table->id)
            ->where('status', QrStatus::ACTIVE)
            ->update(['status' => QrStatus::INACTIVE]);

        $table->delete();
    }

    /**
     * Generate or rotate QR code for a table.
     * Enforces Decision #24 (only one ACTIVE QR per table at a time).
     */
    public function generateQrCode(Table $table): QrCode
    {
        return DB::transaction(function () use ($table) {
            // Deactivate any currently active QR for this table
            QrCode::withoutGlobalScope('store')
                ->where('table_id', $table->id)
                ->where('status', QrStatus::ACTIVE)
                ->update(['status' => QrStatus::INACTIVE]);

            // Generate cryptographically secure 64-char hex token
            $token = bin2hex(random_bytes(32));

            return QrCode::create([
                'id' => (string) Str::ulid(),
                'store_id' => $table->store_id,
                'table_id' => $table->id,
                'token' => $token,
                'status' => QrStatus::ACTIVE,
            ]);
        });
    }

    /**
     * List all QR codes history for a table.
     */
    public function listQrCodes(Table $table): Collection
    {
        return QrCode::withoutGlobalScope('store')
            ->where('table_id', $table->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
