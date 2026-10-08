<?php

namespace App\Services\MiLog;

use App\ApiKey;
use App\Exceptions\UiAuthenticationException;
use App\Tenant;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function issue(Tenant $tenant, User $user, $name, $kind)
    {
        return DB::transaction(function () use ($tenant, $user, $name, $kind) {
            $lockedTenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $entitlements = app(ApiEntitlement::class);

            if (! $entitlements->canCreate($lockedTenant, $kind)) {
                throw new UiAuthenticationException('entitlement_required', 'This account cannot create that credential.', 403);
            }

            $keys = ApiKey::where('tenant_id', $tenant->id)->where('kind', $kind);
            $limit = $kind === 'temporary'
                ? max(1, (int) config('milog.api_keys.temporary_issuance_limit', 2))
                : max(1, (int) config('milog.api_keys.paid_active_limit', 5));
            $count = $kind === 'temporary' ? $keys->count() : $keys->where('status', 'active')->count();

            if ($count >= $limit) {
                throw new UiAuthenticationException('key_limit_reached', 'The credential limit has been reached.', 409);
            }

            $rawKey = 'milog_'.Str::random(40);
            $trialEnd = $lockedTenant->entitlement->trial_ends_at;
            $expiresAt = $kind === 'temporary'
                ? now()->addDays(max(1, (int) config('milog.api_keys.temporary_lifetime_days', 7)))
                : null;

            if ($expiresAt && $trialEnd && $trialEnd->lessThan($expiresAt)) {
                $expiresAt = $trialEnd;
            }

            $key = ApiKey::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'key_prefix' => ApiKey::keyPrefix($rawKey),
                'key_hash' => ApiKey::hashKey($rawKey),
                'kind' => $kind,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'created_by_user_id' => $user->id,
            ]);

            return [$key, $rawKey];
        });
    }

    public function revoke(ApiKey $key)
    {
        if ($key->status !== 'revoked') {
            $key->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_reason' => 'user_requested',
            ])->save();
        }
    }
}
