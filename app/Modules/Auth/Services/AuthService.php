<?php

namespace App\Modules\Auth\Services;

use App\Core\Enums\AuditAction;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserLoginActivity;
use App\Modules\System\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new user and generate access token.
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'], // User model hashes automatically via casts()
            'is_internal' => false,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Authenticate user credentials, generate token, and create an audit log entry.
     *
     * @throws ValidationException
     */
    public function login(
        string $email,
        string $password,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $user = User::where('email', strtolower(trim($email)))->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Please contact support.'],
            ]);
        }

        $tokenName = $deviceName ?: 'web-client';
        $token = $user->createToken($tokenName)->plainTextToken;

        $user->loadMissing(['tenants', 'userRoles.role.permissions']);
        $primaryTenantId = $user->tenants->first()?->id ?? $user->userRoles->first()?->tenant_id;

        // Log LOGIN event to audit_logs
        AuditLog::create([
            'tenant_id' => $primaryTenantId,
            'user_id' => $user->id,
            'action' => AuditAction::LOGIN,
            'module' => 'auth',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Record UserLoginActivity
        $deviceStr = $deviceName ?: ($userAgent ? (str_contains($userAgent, 'Mobile') ? 'Mobile Browser' : 'Web Browser') : 'Web Browser');
        UserLoginActivity::create([
            'user_id' => $user->id,
            'status' => 'SUCCESS',
            'title' => 'Login Berhasil',
            'device' => $deviceStr,
            'ip_address' => $ipAddress ?: '127.0.0.1',
            'location' => 'Jakarta, Indonesia',
            'logged_at' => now(),
        ]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }


    /**
     * Revoke current access token and log LOGOUT event.
     */
    public function logout(User $user, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        // Revoke token currently used for the request
        $user->currentAccessToken()?->delete();

        $primaryTenantId = \App\Core\Context\AppContext::tenantId()
            ?? $user->tenants()->first()?->id
            ?? $user->userRoles()->first()?->tenant_id;

        // Log LOGOUT event to audit_logs
        AuditLog::create([
            'tenant_id' => $primaryTenantId,
            'user_id' => $user->id,
            'action' => AuditAction::LOGOUT,
            'module' => 'auth',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Get user profile with loaded relationships.
     */
    public function getProfile(User $user): User
    {
        return $user->loadMissing(['tenants', 'userRoles.role.permissions']);
    }
}
