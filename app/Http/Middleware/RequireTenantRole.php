<?php

namespace App\Http\Middleware;

use Closure;

class RequireTenantRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        $context = $request->attributes->get('milogTenantContext');

        if (! $context || ! in_array($context->role, $roles, true)) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'You do not have permission to perform this action.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
