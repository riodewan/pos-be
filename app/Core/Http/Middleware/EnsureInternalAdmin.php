<?php

namespace App\Core\Http\Middleware;

use App\Core\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalAdmin
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     * Ensures that the authenticated user is an internal platform administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorized('Unauthenticated.');
        }

        if (!$user->is_active) {
            return $this->unauthorized('User account has been deactivated.');
        }

        if (!$user->isInternalAdmin()) {
            return $this->forbidden('Access restricted to internal platform administrators only.');
        }

        return $next($request);
    }
}
