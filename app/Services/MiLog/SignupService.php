<?php

namespace App\Services\MiLog;

use App\Mail\VerifyMiLogSignup;
use App\SignupVerification;
use App\Tenant;
use App\TenantEntitlement;
use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SignupService
{
    public function register(array $data)
    {
        $email = mb_strtolower(trim($data['email']));

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->resend($email);

            return;
        }

        try {
            $verification = DB::transaction(function () use ($data, $email) {
                $user = User::create([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'password' => Hash::make($data['password']),
                    'status' => 'pending',
                    'terms_accepted_at' => now(),
                ]);
                $tenant = Tenant::create([
                    'name' => trim($data['tenant_name']),
                    'status' => 'pending',
                ]);
                $user->tenants()->attach($tenant->id, ['role' => 'owner', 'status' => 'pending']);

                return $this->replaceVerification($user, $tenant);
            });
        } catch (QueryException $exception) {
            if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
                return;
            }

            throw $exception;
        }

        $this->send($email, $verification);
    }

    public function resend($email)
    {
        $email = mb_strtolower(trim($email));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if (! $user || $user->status !== 'pending') {
            return;
        }

        $verification = DB::transaction(function () use ($user) {
            $tenant = $user->tenants()->wherePivot('role', 'owner')
                ->where('tenants.status', 'pending')->first();

            return $tenant ? $this->replaceVerification($user, $tenant) : null;
        });

        if ($verification) {
            $this->send($email, $verification);
        }
    }

    public function verify($rawToken)
    {
        return DB::transaction(function () use ($rawToken) {
            $verification = SignupVerification::where('token_hash', hash('sha256', $rawToken))
                ->lockForUpdate()->first();

            if (! $verification || $verification->expires_at->isPast()) {
                return false;
            }

            $user = User::find($verification->user_id);
            $tenant = Tenant::find($verification->tenant_id);

            if (! $user || ! $tenant || $user->status !== 'pending' || $tenant->status !== 'pending') {
                return false;
            }

            $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
            $tenant->forceFill(['status' => 'active'])->save();
            $user->tenants()->updateExistingPivot($tenant->id, ['status' => 'active']);
            TenantEntitlement::create([
                'tenant_id' => $tenant->id,
                'trial_ends_at' => now()->addDays(max(1, (int) config('milog.signup.trial_days', 14))),
            ]);
            $verification->delete();

            return true;
        });
    }

    protected function replaceVerification(User $user, Tenant $tenant)
    {
        $rawToken = Str::random(64);
        SignupVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $tenant->id,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addHours(max(1, (int) config('milog.signup.verification_hours', 24))),
            ]
        );

        return $rawToken;
    }

    protected function send($email, $rawToken)
    {
        $baseUrl = rtrim(config('milog.signup.ui_url'), '/');
        $url = $baseUrl.'/verify-email?token='.rawurlencode($rawToken);

        Mail::to($email)->send(new VerifyMiLogSignup($url));
    }
}
