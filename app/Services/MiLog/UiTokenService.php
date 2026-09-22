<?php

namespace App\Services\MiLog;

use App\Exceptions\UiAuthenticationException;
use App\Tenant;
use App\UiRefreshToken;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

class UiTokenService
{
    public function login($email, $password, $tenantId = null)
    {
        $user = User::whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

        if (! $user || $user->status !== 'active' || ! Hash::check($password, $user->password)) {
            throw $this->invalidCredentials();
        }

        $memberships = $user->tenants()
            ->where('tenants.status', 'active')
            ->wherePivot('status', 'active')
            ->get();

        if ($tenantId) {
            $tenant = $memberships->firstWhere('id', $tenantId);

            if (! $tenant) {
                throw $this->invalidCredentials();
            }
        } elseif ($memberships->count() === 1) {
            $tenant = $memberships->first();
        } elseif ($memberships->count() > 1) {
            throw new UiAuthenticationException(
                'tenant_selection_required',
                'Select a tenant to continue.',
                409,
                ['tenants' => $memberships->map(function ($tenant) {
                    return ['id' => $tenant->id, 'name' => $tenant->name];
                })->values()->all()]
            );
        } else {
            throw $this->invalidCredentials();
        }

        return $this->issue($user, $tenant, (string) Str::uuid());
    }

    public function refresh($rawRefreshToken)
    {
        $outcome = DB::transaction(function () use ($rawRefreshToken) {
            $refreshToken = UiRefreshToken::where('token_hash', UiRefreshToken::hashToken($rawRefreshToken))
                ->lockForUpdate()
                ->first();

            if (! $refreshToken) {
                throw new UiAuthenticationException('invalid_refresh_token', 'The refresh token is invalid.');
            }

            if ($refreshToken->used_at || $refreshToken->revoked_at) {
                $this->revokeFamily($refreshToken->family_id, 'refresh_token_reuse');

                return new UiAuthenticationException('refresh_token_reused', 'The refresh token has already been used.');
            }

            if ($refreshToken->expires_at->isPast()) {
                $refreshToken->forceFill(['revoked_at' => now()])->save();

                return new UiAuthenticationException('refresh_token_expired', 'The refresh token has expired.');
            }

            $user = User::find($refreshToken->user_id);
            $tenant = $user ? $user->tenants()
                ->where('tenants.id', $refreshToken->tenant_id)
                ->where('tenants.status', 'active')
                ->wherePivot('status', 'active')
                ->first() : null;

            if (! $user || $user->status !== 'active' || ! $tenant
                || (int) $refreshToken->auth_version !== (int) $user->auth_version) {
                $this->revokeFamily($refreshToken->family_id, 'membership_inactive');

                return new UiAuthenticationException('invalid_refresh_token', 'The refresh token is invalid.');
            }

            $refreshToken->forceFill(['used_at' => now(), 'revoked_at' => now()])->save();
            Passport::token()->newQuery()->whereKey($refreshToken->access_token_id)->update(['revoked' => true]);

            return $this->issue($user, $tenant, $refreshToken->family_id);
        });

        if ($outcome instanceof UiAuthenticationException) {
            throw $outcome;
        }

        return $outcome;
    }

    public function logout($accessTokenId)
    {
        $token = Passport::token()->newQuery()->find($accessTokenId);

        if (! $token || ! $token->is_ui_token) {
            return;
        }

        DB::transaction(function () use ($token) {
            $this->revokeFamily($token->ui_token_family_id, 'logout');
        });
    }

    public function logoutAll(User $user)
    {
        DB::transaction(function () use ($user) {
            $familyIds = Passport::token()->newQuery()
                ->where('user_id', $user->id)
                ->where('is_ui_token', true)
                ->whereNotNull('ui_token_family_id')
                ->pluck('ui_token_family_id');

            Passport::token()->newQuery()
                ->where('user_id', $user->id)
                ->where('is_ui_token', true)
                ->update(['revoked' => true]);

            UiRefreshToken::whereIn('family_id', $familyIds)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });
    }

    public function revokeFamily($familyId, $reason)
    {
        if (! $familyId) {
            return;
        }

        Passport::token()->newQuery()
            ->where('ui_token_family_id', $familyId)
            ->update(['revoked' => true]);

        UiRefreshToken::where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        Log::info('MiLog UI token family revoked', [
            'family_id' => $familyId,
            'reason' => $reason,
        ]);
    }

    protected function issue(User $user, Tenant $tenant, $familyId)
    {
        return DB::transaction(function () use ($user, $tenant, $familyId) {
            $result = $user->createToken('milog-ui', ['ui']);
            $accessToken = $result->getToken();
            $rawRefreshToken = Str::random(80);

            $accessToken->forceFill([
                'tenant_id' => $tenant->id,
                'ui_token_family_id' => $familyId,
                'is_ui_token' => true,
                'auth_version' => $user->auth_version,
            ])->save();

            UiRefreshToken::create([
                'family_id' => $familyId,
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'access_token_id' => $accessToken->id,
                'auth_version' => $user->auth_version,
                'token_hash' => UiRefreshToken::hashToken($rawRefreshToken),
                'expires_at' => now()->addDays(config('milog.ui_auth.refresh_token_days', 30)),
            ]);

            return [
                'access_token' => $result->accessToken,
                'refresh_token' => $rawRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $result->expiresIn,
                'user' => $this->userPayload($user, $tenant),
            ];
        });
    }

    public function userPayload(User $user, Tenant $tenant)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'role' => $tenant->pivot ? $tenant->pivot->role : null,
            ],
        ];
    }

    protected function invalidCredentials()
    {
        return new UiAuthenticationException('invalid_credentials', 'The provided credentials are invalid.');
    }
}
