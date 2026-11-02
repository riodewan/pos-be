<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Core\Enums\TenantStatus;
use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\UserRole;
use App\Modules\Rbac\Services\RbacService;
use App\Modules\Tenant\Models\PlatformApproval;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KybController extends Controller
{
    use ApiResponse;

    private const DOC_KEYS = ['nib', 'npwp', 'siu'];

    /**
     * Submit KYB verification data.
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'business_type' => ['nullable', 'string', 'max:50'],
            'business_category' => ['nullable', 'string', 'max:100'],
            'outlet_count' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'owner_nik' => ['nullable', 'string', 'max:50'],
            'owner_role' => ['nullable', 'string', 'max:50'],
            'owner_email' => ['nullable', 'string', 'email', 'max:150'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'owner_address' => ['nullable', 'string', 'max:500'],
            // Documents: { name, size, data (base64 data URL) }
            'nib_file' => ['nullable', 'array'],
            'npwp_file' => ['nullable', 'array'],
            'siu_file' => ['nullable', 'array'],
            'nib_file.name' => ['nullable', 'string', 'max:255'],
            'npwp_file.name' => ['nullable', 'string', 'max:255'],
            'siu_file.name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $result = DB::transaction(function () use ($request, $validated, $user) {
            // 1. Find or create Tenant for this user
            $tenant = $user->tenants()->first();

            if (!$tenant) {
                // Remove orphan tenants (no members) left by earlier failed attempts that hold this email
                Tenant::withTrashed()
                    ->where('email', $user->email)
                    ->whereDoesntHave('tenantUsers')
                    ->get()
                    ->each(function ($orphan) {
                        PlatformApproval::where('tenant_id', $orphan->id)->delete();
                        $orphan->forceDelete();
                    });

                if (Tenant::where('email', $user->email)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'business_name' => ['Email akun ini sudah terhubung dengan tenant lain.'],
                    ]);
                }

                $baseSlug = Str::slug($validated['business_name']) ?: 'toko';

                $tenant = Tenant::create([
                    'name' => $validated['business_name'],
                    'slug' => $baseSlug . '-' . strtolower(Str::random(5)),
                    'email' => $user->email,
                    'phone' => $validated['owner_phone'] ?? null,
                    'address' => $validated['business_address'] ?? null,
                    'status' => TenantStatus::PENDING,
                ]);

                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'joined_at' => now(),
                ]);

                // Default tenant roles (Owner, Manager, Kasir, ...) and assign Owner to registrant
                $defaultRoles = app(RbacService::class)->createDefaultTenantRoles($tenant);
                $ownerRole = $defaultRoles['Owner']
                    ?? Role::where('tenant_id', $tenant->id)->where('name', 'Owner')->first();

                if ($ownerRole) {
                    UserRole::firstOrCreate([
                        'user_id' => $user->id,
                        'role_id' => $ownerRole->id,
                        'tenant_id' => $tenant->id,
                    ]);
                }
            } else {
                $tenant->update([
                    'name' => $validated['business_name'],
                    'phone' => $validated['owner_phone'] ?? $tenant->phone,
                    'address' => $validated['business_address'] ?? $tenant->address,
                    'status' => TenantStatus::PENDING,
                ]);
            }

            // 2. Store uploaded documents (base64) on public disk
            $previous = PlatformApproval::where('tenant_id', $tenant->id)
                ->where('type', 'KYB_VERIFICATION')
                ->latest()
                ->first();
            $previousPayload = $previous?->payload ?? [];

            $payload = collect($validated)->except(['nib_file', 'npwp_file', 'siu_file'])->all();

            foreach (self::DOC_KEYS as $key) {
                $field = $key . '_file';
                $file = $request->input($field);

                if (is_array($file) && !empty($file['data']) && str_contains($file['data'], ',')) {
                    [, $b64] = explode(',', $file['data'], 2);
                    $binary = base64_decode($b64, true);
                    if ($binary !== false) {
                        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) ?: 'pdf';
                        $ext = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true) ? $ext : 'pdf';
                        $path = "kyb/{$tenant->id}/{$key}-" . Str::random(8) . ".{$ext}";
                        Storage::disk('public')->put($path, $binary);
                        $payload[$field] = [
                            'name' => $file['name'] ?? basename($path),
                            'size' => $file['size'] ?? null,
                            'path' => $path,
                            'url' => Storage::disk('public')->url($path),
                        ];
                        continue;
                    }
                }

                // Keep previously uploaded document if user did not replace it
                if (!empty($previousPayload[$field])) {
                    $payload[$field] = $previousPayload[$field];
                }
            }

            $payload['submitted_at'] = now()->toIso8601String();
            $payload['user_email'] = $user->email;
            $payload['user_name'] = $user->name;

            // 3. Create or reset PlatformApproval
            if ($previous) {
                $previous->update([
                    'title' => 'Verifikasi KYB - ' . $tenant->name,
                    'description' => 'Pengajuan ulang verifikasi dokumen & legalitas bisnis.',
                    'status' => 'PENDING',
                    'payload' => $payload,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                ]);
                $approval = $previous;
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

            return compact('tenant', 'approval');
        });

        return $this->success($result, 'Data verifikasi bisnis berhasil dikirim dan sedang menunggu persetujuan Super Admin.');
    }

    /**
     * Active business categories from master data (for KYB form).
     */
    public function categories(): JsonResponse
    {
        $categories = \App\Modules\Tenant\Models\PlatformMasterCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        return $this->success($categories);
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
