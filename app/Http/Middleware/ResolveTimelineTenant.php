<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class ResolveTimelineTenant
{
    protected $apiKeys;

    protected $uiTenants;

    public function __construct(ResolveTenantFromApiKey $apiKeys, ResolveUiTenant $uiTenants)
    {
        $this->apiKeys = $apiKeys;
        $this->uiTenants = $uiTenants;
    }

    public function handle($request, Closure $next)
    {
        $apiKeyHeader = config('milog.api_keys.header', 'X-API-Key');

        if ($request->hasHeader($apiKeyHeader) || ! $request->bearerToken()) {
            return $this->apiKeys->handle($request, $next);
        }

        $user = Auth::guard('api')->user();

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'Provide a valid API key or bearer token.',
                ],
            ], 401);
        }

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $this->uiTenants->handle($request, $next);
    }
}
