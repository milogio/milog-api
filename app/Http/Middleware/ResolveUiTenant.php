<?php

namespace App\Http\Middleware;

use App\Services\MiLog\TenantContext;
use Closure;
use Laravel\Passport\Passport;

class ResolveUiTenant
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        $currentToken = $user ? $user->token() : null;
        $token = $currentToken ? Passport::token()->newQuery()->find($currentToken->getKey()) : null;

        if (! $token || ! $token->is_ui_token || $token->revoked || ! $token->tenant_id
            || (int) $token->auth_version !== (int) $user->auth_version || $user->status !== 'active') {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Unauthenticated.']], 401);
        }

        $tenant = $user->tenants()
            ->where('tenants.id', $token->tenant_id)
            ->where('tenants.status', 'active')
            ->wherePivot('status', 'active')
            ->first();

        if (! $tenant) {
            $token->revoke();

            return response()->json(['error' => ['code' => 'membership_inactive', 'message' => 'Tenant access is no longer active.']], 401);
        }

        $context = new TenantContext($tenant, $user, $tenant->pivot->role);
        $request->attributes->set('milogTenant', $tenant);
        $request->attributes->set('milogTenantContext', $context);
        app()->instance(TenantContext::class, $context);

        return $next($request);
    }
}
