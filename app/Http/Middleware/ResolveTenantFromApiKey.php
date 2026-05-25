<?php

namespace App\Http\Middleware;

use App\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;

class ResolveTenantFromApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $header = config('milog.api_keys.header', 'X-API-Key');
        $rawKey = $request->header($header);

        if (empty($rawKey)) {
            return $this->unauthorizedResponse();
        }

        $apiKey = ApiKey::with('tenant')
            ->where('key_hash', ApiKey::hashKey($rawKey))
            ->first();

        if (! $apiKey || ! $apiKey->tenant) {
            return $this->unauthorizedResponse();
        }

        $apiKey->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('milogTenant', $apiKey->tenant);
        $request->attributes->set('milogApiKey', $apiKey);

        return $next($request);
    }

    /**
     * Build the standard unauthorized response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorizedResponse()
    {
        return new JsonResponse([
            'message' => 'Invalid API key.',
        ], 401);
    }
}
