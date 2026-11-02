<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Enums\TenantStatus;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\Tenant\Models\PlatformApproval;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KybController extends Controller
{
    use ApiResponse;

    /**
     * Submit KYB verification data.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:50'],
            'business_category' => ['nullable', 'string', 'max:100'],
            'outlet_count' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'nib_file' => ['nullable'],
            'npwp_file' => ['nullable'],
            'siu_file' => ['nullable'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'owner_nik' => ['nullable', 'string', 'max:50'],
            'owner_role' => ['nullable', 'string', 'max:50'],
            'owner_email' => ['nullable', 'string', 'email', 'max:150'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'owner_address' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        // 1. Find or create Tenant for this user
        $tenant = $user->tenants()->first();

        if (!$tenant) {
            $baseSlug = Str::slug($validated['business_name']) ?: 'toko';
            $uniqueSlug = $baseSlug . '-' . strtolower(Str::random(5));

            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'slug' => $uniqueSlug,
                'email' => $validated['owner_email'] ?? $user->email,
                'phone' => $validated['owner_phone'] ?? '081234567890',
                'address' => $validated['business_address'] ?? 'Indonesia',
                'status' => TenantStatus::PENDING,
            ]);

            // Link user to tenant
            $tenant->users()->attach($user->id, ['joined_at' => now()]);

            // Ensure Owner role
            $ownerRole = Role::where('tenant_id', $tenant->id)->where('name', 'Owner')->first();
            if (!$ownerRole) {
                $ownerRole = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Owner',
                    'guard_name' => 'web',
                    'scope' => 'TENANT',
                    'description' => 'Tenant Owner / Pemilik Toko',
                ]);
            }

            UserRole::firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $ownerRole->id,
                'tenant_id' => $tenant->id,
            ]);
        } else {
            // Update tenant details and keep pending review
            $tenant->update([
                'name' => $validated['business_name'],
                'phone' => $validated['owner_phone'] ?? $tenant->phone,
                'address' => $validated['business_address'] ?? $tenant->address,
                'status' => TenantStatus::PENDING,
            ]);
        }

        // 2. Create or update PlatformApproval
        $approval = PlatformApproval::where('tenant_id', $tenant->id)
            ->where('type', 'KYB_VERIFICATION')
            ->latest()
            ->first();

        $payload = array_merge($validated, [
            'submitted_at' => now()->toIso8601String(),
            'user_email' => $user->email,
            'user_name' => $user->name,
        ]);

        if ($approval) {
            $approval->update([
                'title' => 'Verifikasi KYB - ' . $tenant->name,
                'description' => 'Pengajuan ulang verifikasi dokumen & legalitas bisnis.',
                'status' => 'PENDING',
                'payload' => $payload,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);
        } else {
            $approval = PlatformApproval::create([
                'type' => 'KYB_VERIFICATION',
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'title' => 'Verifikasi KYB - ' . $tenant->name,
                'description' => 'Pengajuan verifikasi informasi bisnis dan dokumen KYB dari merchant.',
                'status' => 'PENDING',
                'payload' => $payload,
            ]);
        }

        return $this->success([
            'tenant' => $tenant,
            'approval' => $approval,
        ], 'Data verifikasi bisnis berhasil dikirim dan sedang menunggu persetujuan Super Admin.');
    }

    /**
     * Get KYB status for authenticated merchant.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->tenants()->first();

        if (!$tenant) {
            return $this->success([
                'has_submitted' => false,
                'status' => 'NOT_SUBMITTED',
                'tenant' => null,
                'approval' => null,
            ]);
        }

        $approval = PlatformApproval::where('tenant_id', $tenant->id)
            ->where('type', 'KYB_VERIFICATION')
            ->latest()
            ->first();

        $effectiveStatus = 'NOT_SUBMITTED';
        if ($approval) {
            $effectiveStatus = $approval->status; // PENDING, APPROVED, REJECTED
        } elseif ($tenant->status === TenantStatus::ACTIVE) {
            $effectiveStatus = 'APPROVED';
        } elseif ($tenant->status === TenantStatus::PENDING) {
            $effectiveStatus = 'PENDING';
        }

        return $this->success([
            'has_submitted' => $approval !== null,
            'status' => $effectiveStatus,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status instanceof TenantStatus ? $tenant->status->value : $tenant->status,
            ],
            'approval' => $approval,
        ]);
    }
}
