<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Core\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new user account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Registration successful');
    }

    /**
     * Authenticate user credentials and return access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email: $request->input('email'),
            password: $request->input('password'),
            deviceName: $request->input('device_name'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    /**
     * Invalidate current access token and log out.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            user: $request->user(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Retrieve authenticated user profile with roles and tenants.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $this->authService->getProfile($request->user());

        return $this->success(new UserResource($user), 'User profile retrieved');
    }
}
