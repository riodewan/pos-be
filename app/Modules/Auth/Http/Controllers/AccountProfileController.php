<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserLoginActivity;
use App\Modules\Tenant\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class AccountProfileController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['userRoles.role', 'tenants']);

        $primaryRole = $user->userRoles->first()?->role?->name 
            ?? ($user->is_internal ? 'Admin Internal' : 'Staff');

        $tenant = $user->tenants->first();
        $outletCount = $tenant ? Store::where('tenant_id', $tenant->id)->count() : 1;

        $lastActivity = UserLoginActivity::where('user_id', $user->id)
            ->orderBy('logged_at', 'desc')
            ->first();

        $rawNotifs = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        $notifications = [
            'orders' => [
                'email' => isset($rawNotifs['orders']['email']) ? (bool) $rawNotifs['orders']['email'] : (isset($rawNotifs['order_email']) ? (bool) $rawNotifs['order_email'] : true),
                'inApp' => isset($rawNotifs['orders']['inApp']) ? (bool) $rawNotifs['orders']['inApp'] : true,
            ],
            'transactions' => [
                'email' => isset($rawNotifs['transactions']['email']) ? (bool) $rawNotifs['transactions']['email'] : true,
                'inApp' => isset($rawNotifs['transactions']['inApp']) ? (bool) $rawNotifs['transactions']['inApp'] : true,
            ],
            'reservations' => [
                'email' => isset($rawNotifs['reservations']['email']) ? (bool) $rawNotifs['reservations']['email'] : true,
                'inApp' => isset($rawNotifs['reservations']['inApp']) ? (bool) $rawNotifs['reservations']['inApp'] : true,
            ],
            'dailySummary' => [
                'email' => isset($rawNotifs['dailySummary']['email']) ? (bool) $rawNotifs['dailySummary']['email'] : (isset($rawNotifs['sales_summary']) ? (bool) $rawNotifs['sales_summary'] : true),
                'inApp' => isset($rawNotifs['dailySummary']['inApp']) ? (bool) $rawNotifs['dailySummary']['inApp'] : true,
            ],
        ];

        $rawPrefs = is_array($user->user_preferences) ? $user->user_preferences : [];
        $preferences = [
            'language' => $rawPrefs['language'] ?? 'Bahasa Indonesia',
            'dateFormat' => $rawPrefs['dateFormat'] ?? 'DD/MM/YYYY',
            'timezone' => $rawPrefs['timezone'] ?? 'WIB (UTC+7)',
        ];

        return response()->json([
            'message' => 'User profile retrieved successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'job_title' => $user->job_title ?? $primaryRole,
                'role' => $primaryRole,
                'outlet_count' => $outletCount,
                'avatar_url' => $user->avatar_url,
                'joined_date' => $user->created_at ? $user->created_at->format('d M Y') : '-',
                'last_login' => [
                    'datetime' => $lastActivity ? $lastActivity->logged_at->format('d M Y, H:i') : '-',
                    'location' => $lastActivity ? ($lastActivity->location ?: ($lastActivity->ip_address ?: 'Jakarta, Indonesia')) : 'Jakarta, Indonesia',
                ],
                'notification_preferences' => $notifications,
                'user_preferences' => $preferences,
                'is_internal' => $user->is_internal,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'string'],
            'notification_preferences' => ['nullable', 'array'],
            'user_preferences' => ['nullable', 'array'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Kata sandi saat ini tidak cocok.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui.',
        ]);
    }

    public function loginActivities(Request $request): JsonResponse
    {
        $user = $request->user();

        $activities = UserLoginActivity::where('user_id', $user->id)
            ->orderBy('logged_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'message' => 'Login activities retrieved successfully.',
            'data' => $activities,
        ]);
    }
}
