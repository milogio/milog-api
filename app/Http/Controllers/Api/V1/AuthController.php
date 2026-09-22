<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\UiAuthenticationException;
use App\Http\Controllers\Controller;
use App\Services\MiLog\UiTokenService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $tokens;

    public function __construct(UiTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'tenant_id' => ['nullable', 'uuid'],
        ]);

        return $this->respond(function () use ($data) {
            return $this->tokens->login($data['email'], $data['password'], $data['tenant_id'] ?? null);
        });
    }

    public function refresh(Request $request)
    {
        $data = $request->validate(['refresh_token' => ['required', 'string', 'max:255']]);

        return $this->respond(function () use ($data) {
            return $this->tokens->refresh($data['refresh_token']);
        });
    }

    public function logout(Request $request)
    {
        $this->tokens->logout($request->user()->token()->getKey());

        return response()->noContent();
    }

    public function logoutAll(Request $request)
    {
        $this->tokens->logoutAll($request->user());

        return response()->noContent();
    }

    public function me(Request $request)
    {
        $context = $request->attributes->get('milogTenantContext');

        return response()->json([
            'user' => $this->tokens->userPayload($request->user(), $context->tenant),
            'expires_at' => $request->user()->token()->expires_at,
        ]);
    }

    protected function respond(callable $callback)
    {
        try {
            return response()->json($callback());
        } catch (UiAuthenticationException $exception) {
            return response()->json([
                'error' => array_merge([
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ], $exception->details()),
            ], $exception->status());
        }
    }
}
