<?php

namespace App\Modules\System\Services;

use App\Core\Context\AppContext;
use App\Modules\System\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SettingService
{
    /**
     * Hierarchical resolution of a setting value: Store -> Tenant -> System.
     * Sesuai Decision #8.
     */
    public function get(string $key, ?string $storeId = null, ?string $tenantId = null, mixed $default = null): mixed
    {
        $tenantId = $tenantId ?? AppContext::tenantId();
        $storeId = $storeId ?? AppContext::storeId();

        // 1. Check Store-level setting
        if ($storeId && $tenantId) {
            $storeSetting = Setting::where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('key', $key)
                ->first();

            if ($storeSetting !== null) {
                return $storeSetting->value;
            }
        }

        // 2. Check Tenant-level setting
        if ($tenantId) {
            $tenantSetting = Setting::where('tenant_id', $tenantId)
                ->whereNull('store_id')
                ->where('key', $key)
                ->first();

            if ($tenantSetting !== null) {
                return $tenantSetting->value;
            }
        }

        // 3. Check System-level setting (global fallback)
        $systemSetting = Setting::whereNull('tenant_id')
            ->whereNull('store_id')
            ->where('key', $key)
            ->first();

        if ($systemSetting !== null) {
            return $systemSetting->value;
        }

        return $default;
    }

    /**
     * Upsert a setting at the specified scope (Store, Tenant, or System).
     */
    public function set(string $key, string $value, ?string $tenantId = null, ?string $storeId = null): Setting
    {
        $tenantId = $tenantId ?? AppContext::tenantId();
        $storeId = $storeId ?? AppContext::storeId();

        $query = Setting::where('key', $key);

        if ($storeId) {
            $query->where('tenant_id', $tenantId)->where('store_id', $storeId);
        } elseif ($tenantId) {
            $query->where('tenant_id', $tenantId)->whereNull('store_id');
        } else {
            $query->whereNull('tenant_id')->whereNull('store_id');
        }

        $setting = $query->first();

        if ($setting) {
            $setting->update(['value' => $value]);
            return $setting;
        }

        return Setting::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * Batch upsert settings dictionary.
     */
    public function setBatch(array $settings, ?string $tenantId = null, ?string $storeId = null): array
    {
        $results = [];
        foreach ($settings as $key => $value) {
            $results[$key] = $this->set((string) $key, (string) $value, $tenantId, $storeId);
        }

        return $results;
    }

    /**
     * List all effective resolved settings (hierarchical merge: System -> Tenant -> Store).
     */
    public function listResolvedSettings(?string $tenantId = null, ?string $storeId = null): array
    {
        $tenantId = $tenantId ?? AppContext::tenantId();
        $storeId = $storeId ?? AppContext::storeId();

        $resolved = [];

        // 1. System level
        $systemSettings = Setting::whereNull('tenant_id')
            ->whereNull('store_id')
            ->get();
        foreach ($systemSettings as $s) {
            $resolved[$s->key] = [
                'value' => $s->value,
                'source' => 'SYSTEM',
            ];
        }

        // 2. Tenant level override
        if ($tenantId) {
            $tenantSettings = Setting::where('tenant_id', $tenantId)
                ->whereNull('store_id')
                ->get();
            foreach ($tenantSettings as $s) {
                $resolved[$s->key] = [
                    'value' => $s->value,
                    'source' => 'TENANT',
                ];
            }
        }

        // 3. Store level override
        if ($tenantId && $storeId) {
            $storeSettings = Setting::where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->get();
            foreach ($storeSettings as $s) {
                $resolved[$s->key] = [
                    'value' => $s->value,
                    'source' => 'STORE',
                ];
            }
        }

        return $resolved;
    }
}
