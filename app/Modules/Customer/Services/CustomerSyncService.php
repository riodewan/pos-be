<?php

namespace App\Modules\Customer\Services;

use App\Core\Enums\OrderStatus;
use App\Modules\Customer\Models\Customer;
use App\Modules\PosOrder\Models\Order;
use App\Modules\Tenant\Models\Store;
use Illuminate\Support\Str;

class CustomerSyncService
{
    /**
     * Generic walk-in / placeholder customer names that should NOT create a dedicated CRM profile
     * unless accompanied by a valid phone number.
     */
    protected const GENERIC_NAMES = [
        'pelanggan umum',
        'pelanggan',
        'walk-in',
        'walk in',
        'walkin',
        'guest',
        'customer',
        'umum',
        'tanpa nama',
        'anonim',
        'anonymous',
        'kasir',
        '-',
    ];

    /**
     * Normalize Indonesian phone numbers to standard '08...' format.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', trim($phone));
        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '0062')) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0' . $digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 16) {
            return null;
        }

        return $digits;
    }

    /**
     * Clean and validate a customer name. Returns null if it is empty or a generic placeholder.
     */
    public function normalizeName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        $cleaned = preg_replace('/\s+/', ' ', trim($name));
        if (mb_strlen($cleaned) < 2) {
            return null;
        }

        $lower = mb_strtolower($cleaned);
        if (in_array($lower, self::GENERIC_NAMES, true)) {
            return null;
        }

        // Ignore auto table placeholders like "Meja 01", "Table 2", "TA-001"
        if (preg_match('/^(meja|table|ta-|qr-)\s*[0-9a-z\-]*$/i', $lower)) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Strip common honorifics/titles and normalize for fuzzy name comparison.
     */
    public function canonicalizeNameForComparison(string $name): string
    {
        $lower = mb_strtolower(trim($name));
        $lower = preg_replace('/[^a-z0-9\s]/u', ' ', $lower);
        $lower = preg_replace('/\b(bpk|bapak|pak|ibu|bu|kak|mas|mba|mbak|sdr|sdri|tn|ny|mr|mrs|ms)\b/u', ' ', $lower);
        $lower = preg_replace('/\s+/', ' ', trim($lower));

        return $lower;
    }

    /**
     * Determine whether two customer names are similar enough to represent the same person.
     */
    public function areNamesSimilar(?string $nameA, ?string $nameB): bool
    {
        if (!$nameA || !$nameB) {
            return false;
        }

        $canonA = $this->canonicalizeNameForComparison($nameA);
        $canonB = $this->canonicalizeNameForComparison($nameB);

        if ($canonA === '' || $canonB === '') {
            return false;
        }

        if ($canonA === $canonB) {
            return true;
        }

        // Check if one is a prefix/subset of the other with the same first name (e.g., "Budi" vs "Budi Santoso")
        $wordsA = explode(' ', $canonA);
        $wordsB = explode(' ', $canonB);

        if ($wordsA[0] === $wordsB[0] && mb_strlen($wordsA[0]) >= 4) {
            if (count($wordsA) === 1 || count($wordsB) === 1) {
                return true;
            }
            if (str_starts_with($canonA, $canonB) || str_starts_with($canonB, $canonA)) {
                return true;
            }
        }

        // Levenshtein distance for typos on medium/long names (e.g. "Marshanda" vs "Marsanda")
        if (mb_strlen($canonA) >= 5 && mb_strlen($canonB) >= 5 && strlen($canonA) < 250 && strlen($canonB) < 250) {
            $lev = levenshtein($canonA, $canonB);
            if ($lev <= 1 || ($lev <= 2 && min(mb_strlen($canonA), mb_strlen($canonB)) >= 8)) {
                return true;
            }
        }

        // Percentage similarity check
        similar_text($canonA, $canonB, $percent);
        if ($percent >= 84.0) {
            return true;
        }

        return false;
    }

    /**
     * Find an existing customer by phone equality or name similarity, or create a new customer automatically.
     */
    public function resolveOrCreateFromOrderData(string $tenantId, ?string $rawName, ?string $rawPhone): ?Customer
    {
        $cleanName = $this->normalizeName($rawName);
        $cleanPhone = $this->normalizePhone($rawPhone);

        // If neither a valid name nor a valid phone is provided, do not create a customer
        if (!$cleanName && !$cleanPhone) {
            return null;
        }

        $existingCustomers = Customer::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'asc')
            ->get();

        // 1. Primary Match: Same normalized phone number
        if ($cleanPhone) {
            foreach ($existingCustomers as $customer) {
                $custPhone = $this->normalizePhone($customer->phone);
                if ($custPhone && $custPhone === $cleanPhone) {
                    $updates = [];
                    if ($customer->phone !== $cleanPhone) {
                        $updates['phone'] = $cleanPhone;
                    }
                    // Upgrade name if the new name is more complete (e.g., "Budi" -> "Budi Santoso")
                    if ($cleanName && (!$this->normalizeName($customer->name) || mb_strlen($cleanName) > mb_strlen($customer->name))) {
                        $updates['name'] = $cleanName;
                    }
                    if (!empty($updates)) {
                        $customer->update($updates);
                    }
                    return $customer;
                }
            }
        }

        // 2. Secondary Match: Similar name (when phone is identical or one side has no phone yet)
        if ($cleanName) {
            foreach ($existingCustomers as $customer) {
                if ($this->areNamesSimilar($customer->name, $cleanName)) {
                    $custPhone = $this->normalizePhone($customer->phone);

                    // Only merge by name similarity if neither has conflicting phone numbers
                    if (!$cleanPhone || !$custPhone || $cleanPhone === $custPhone) {
                        $updates = [];
                        if ($cleanPhone && !$custPhone) {
                            $updates['phone'] = $cleanPhone;
                        }
                        if (mb_strlen($cleanName) > mb_strlen((string) $customer->name)) {
                            $updates['name'] = $cleanName;
                        }
                        if (!empty($updates)) {
                            $customer->update($updates);
                        }
                        return $customer;
                    }
                }
            }
        }

        // 3. Create new customer automatically
        $displayName = $cleanName ?: ('Pelanggan ' . substr((string) $cleanPhone, -4));

        return Customer::withoutGlobalScope('tenant')->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'name' => $displayName,
            'phone' => $cleanPhone,
            'tier' => 'Bronze',
            'status' => 'Aktif',
            'segmentation' => 'Baru',
            'notes' => 'Terdaftar otomatis dari transaksi pesanan',
            'points' => 0,
            'total_spent' => 0,
            'total_visits' => 0,
        ]);
    }

    /**
     * Recalculate total_visits, total_spent, points, last_visit_at, tier & segmentation for a customer.
     */
    public function recalculateCustomerStats(Customer $customer): Customer
    {
        $storeIds = Store::where('tenant_id', $customer->tenant_id)->pluck('id');

        $orders = Order::withoutGlobalScope('store')
            ->whereIn('store_id', $storeIds)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->get();

        $totalVisits = $orders->count();
        $totalSpent = (float) $orders->sum(fn ($o) => max(0, (float) $o->total_amount - (float) $o->service_charge));
        $points = (int) floor($totalSpent / 10000);
        $lastVisit = $orders->max('created_at');

        // Auto-upgrade tier & segmentation based on visits & spending
        $segmentation = 'Baru';
        $tier = 'Bronze';

        if ($totalVisits >= 5 || $totalSpent >= 500000) {
            $segmentation = 'VIP';
            $tier = 'Gold';
        } elseif ($totalVisits >= 2 || $totalSpent >= 150000) {
            $segmentation = 'Regular';
            $tier = 'Silver';
        }

        $customer->update([
            'total_visits' => $totalVisits,
            'total_spent' => round($totalSpent, 2),
            'points' => $points,
            'last_visit_at' => $lastVisit ?: $customer->last_visit_at,
            'segmentation' => $segmentation,
            'tier' => $tier,
        ]);

        return $customer->fresh();
    }

    /**
     * Scan all orders in a tenant, deduplicate customers with matching phone / similar name,
     * link unlinked orders, and recalculate customer loyalty metrics.
     */
    public function syncAllTenantOrders(string $tenantId): array
    {
        $storeIds = Store::where('tenant_id', $tenantId)->pluck('id');
        if ($storeIds->isEmpty()) {
            return [
                'linked_orders' => 0,
                'merged_customers' => 0,
                'total_customers' => 0,
            ];
        }

        // Step 1: Deduplicate existing customers in this tenant by same phone or similar name
        $mergedCount = $this->deduplicateTenantCustomers($tenantId);

        // Step 2: Find all non-cancelled orders in this tenant's stores that have customer_name or customer_phone
        $ordersToSync = Order::withoutGlobalScope('store')
            ->whereIn('store_id', $storeIds)
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->whereNull('customer_id')
            ->where(function ($q) {
                $q->whereNotNull('customer_name')
                  ->orWhereNotNull('customer_phone');
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $linkedOrders = 0;

        foreach ($ordersToSync as $order) {
            $customer = $this->resolveOrCreateFromOrderData(
                $tenantId,
                $order->customer_name,
                $order->customer_phone
            );

            if ($customer) {
                $order->update(['customer_id' => $customer->id]);
                $linkedOrders++;
            }
        }

        // Step 3: Also link any orders whose customer_phone or customer_name matches an existing customer
        $allCustomers = Customer::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->get();

        foreach ($allCustomers as $customer) {
            $this->recalculateCustomerStats($customer);
        }

        return [
            'linked_orders' => $linkedOrders,
            'merged_customers' => $mergedCount,
            'total_customers' => $allCustomers->count(),
        ];
    }

    /**
     * Merge duplicate customer records within a tenant that share the same normalized phone
     * or similar name without conflicting phones.
     */
    protected function deduplicateTenantCustomers(string $tenantId): int
    {
        $customers = Customer::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'asc')
            ->get();

        $kept = [];
        $mergedCount = 0;

        foreach ($customers as $candidate) {
            $candPhone = $this->normalizePhone($candidate->phone);
            $candName = $this->normalizeName($candidate->name) ?? $candidate->name;
            $matchedPrimary = null;

            foreach ($kept as $primary) {
                $primPhone = $this->normalizePhone($primary->phone);

                // Match 1: Exact same normalized phone
                if ($candPhone && $primPhone && $candPhone === $primPhone) {
                    $matchedPrimary = $primary;
                    break;
                }

                // Match 2: Similar name and no conflicting phone
                if ($this->areNamesSimilar($primary->name, $candName)) {
                    if (!$candPhone || !$primPhone || $candPhone === $primPhone) {
                        $matchedPrimary = $primary;
                        break;
                    }
                }
            }

            if ($matchedPrimary) {
                // Reassign all orders from duplicate candidate to primary customer
                Order::withoutGlobalScope('store')
                    ->where('customer_id', $candidate->id)
                    ->update(['customer_id' => $matchedPrimary->id]);

                $updates = [];
                if ($candPhone && !$this->normalizePhone($matchedPrimary->phone)) {
                    $updates['phone'] = $candPhone;
                }
                if ($candName && mb_strlen($candName) > mb_strlen((string) $matchedPrimary->name)) {
                    $updates['name'] = $candName;
                }
                if ($candidate->email && !$matchedPrimary->email) {
                    $updates['email'] = $candidate->email;
                }
                if (!empty($updates)) {
                    $matchedPrimary->update($updates);
                }

                $candidate->delete();
                $mergedCount++;
            } else {
                if ($candPhone && $candidate->phone !== $candPhone) {
                    $candidate->update(['phone' => $candPhone]);
                }
                $kept[] = $candidate;
            }
        }

        return $mergedCount;
    }
}

