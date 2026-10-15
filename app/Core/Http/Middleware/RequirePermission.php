<?php

namespace App\Core\Http\Middleware;

use App\Core\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param string ...$permissions One or more permissions required (logical OR or comma-separated)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorized('Unauthenticated.');
        }

        if (!$user->is_active) {
            return $this->unauthorized('User account has been deactivated.');
        }

        if (empty($permissions)) {
            return $next($request);
        }

        // Support passing multiple comma-separated permissions e.g. "product.view,product.create"
        $flatPermissions = [];
        foreach ($permissions as $p) {
            foreach (explode(',', $p) as $sub) {
                $trimmed = trim($sub);
                if ($trimmed !== '') {
                    $flatPermissions[] = $trimmed;
                }
            }
        }

        // Check if user has at least one of the required permissions
        $hasAnyPermission = false;
        foreach ($flatPermissions as $permission) {
            if ($user->hasPermission($permission)) {
                $hasAnyPermission = true;
                break;
            }
        }

        if (!$hasAnyPermission) {
            return $this->forbidden(
                'You do not have the required permission: ' . implode(' or ', $flatPermissions)
            );
        }

        return $next($request);
    }
}
