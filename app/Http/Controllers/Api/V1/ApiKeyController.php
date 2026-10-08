<?php

namespace App\Http\Controllers\Api\V1;

use App\ApiKey;
use App\Exceptions\UiAuthenticationException;
use App\Http\Controllers\Controller;
use App\Services\MiLog\ApiEntitlement;
use App\Services\MiLog\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('milogTenant');

        return response()->json([
            'data' => $tenant->apiKeys()->orderByDesc('created_at')->get()->map(function ($key) {
                return $this->metadata($key);
            }),
        ]);
    }

    public function store(Request $request, ApiKeyService $keys)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'in:temporary,paid'],
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            return response()->json([
                'error' => ['code' => 'invalid_credentials', 'message' => 'The provided credentials are invalid.'],
            ], 401);
        }

        try {
            [$key, $rawKey] = $keys->issue(
                $request->attributes->get('milogTenant'),
                $request->user(),
                trim($data['name']),
                $data['kind']
            );
        } catch (UiAuthenticationException $exception) {
            return response()->json([
                'error' => ['code' => $exception->errorCode(), 'message' => $exception->getMessage()],
            ], $exception->status());
        }

        return response()->json([
            'data' => $this->metadata($key),
            'api_key' => $rawKey,
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, ApiKeyService $keys, $key)
    {
        $tenant = $request->attributes->get('milogTenant');
        $apiKey = $tenant->apiKeys()->whereKey($key)->firstOrFail();
        $keys->revoke($apiKey);

        return response()->noContent();
    }

    public function entitlement(Request $request, ApiEntitlement $entitlements)
    {
        return response()->json([
            'data' => $entitlements->payload($request->attributes->get('milogTenant')),
        ]);
    }

    protected function metadata(ApiKey $key)
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'key_prefix' => $key->key_prefix,
            'kind' => $key->kind,
            'status' => $key->status,
            'created_at' => $key->created_at,
            'expires_at' => $key->expires_at,
            'revoked_at' => $key->revoked_at,
            'last_used_at' => $key->last_used_at,
        ];
    }
}
