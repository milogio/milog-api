<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MiLog\SignupService;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    public function register(Request $request, SignupService $signups)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tenant_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:1024', 'confirmed'],
            'terms_accepted' => ['accepted'],
        ]);

        $signups->register($data);

        return response()->json(['message' => 'If this address is eligible, a verification email has been sent.'], 202);
    }

    public function resend(Request $request, SignupService $signups)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $signups->resend($data['email']);

        return response()->json(['message' => 'If this address is eligible, a verification email has been sent.'], 202);
    }

    public function verify(Request $request, SignupService $signups)
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        if (! $signups->verify($data['token'])) {
            return response()->json([
                'error' => ['code' => 'invalid_verification', 'message' => 'The verification link is invalid or expired.'],
            ], 422);
        }

        return response()->json(['message' => 'Account verified. You can now sign in.']);
    }
}
