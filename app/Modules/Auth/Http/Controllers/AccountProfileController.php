<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserLoginActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountProfileController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['userRoles.role', 'tenants']);

        return response()->json([
            'message' => 'User profile retrieved successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '+62 812-3456-7890',
                'job_title' => $user->job_title ?? 'Store Manager',
                'avatar_url' => $user->avatar_url,
                'notification_preferences' => $user->notification_preferences ?? [
                    'order_email' => true,
                    'stock_warning' => true,
                    'promo_push' => false,
                    'sales_summary' => true,
                ],
                'user_preferences' => $user->user_preferences ?? [
                    'theme' => 'light',
                    'language' => 'id',
                    'compact_view' => false,
                ],
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
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'notification_preferences' => ['nullable', 'array'],
            'user_preferences' => ['nullable', 'array'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $user,
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
