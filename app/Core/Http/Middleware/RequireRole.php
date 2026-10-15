<?php

namespace App\Core\Http\Middleware;

use App\Core\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param string ...$roles One or more roles allowed (logical OR)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorized('Unauthenticated.');
        }

        if (!$user->is_active) {
            return $this->unauthorized('User account has been deactivated.');
        }

        if (empty($roles)) {
            return $next($request);
        }

        $flatRoles = [];
        foreach ($roles as $r) {
            foreach (explode(',', $r) as $sub) {
                $trimmed = trim($sub);
                if ($trimmed !== '') {
                    $flatRoles[] = $trimmed;
                }
            }
        }

        if (!$user->hasRole($flatRoles)) {
            return $this->forbidden(
                'You do not have the required role: ' . implode(' or ', $flatRoles)
            );
        }

        return $next($request);
    }
}
