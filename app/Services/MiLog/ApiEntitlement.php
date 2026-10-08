<?php

namespace App\Services\MiLog;

use App\ApiKey;
use App\Tenant;

class ApiEntitlement
{
    public function state(Tenant $tenant)
    {
        $entitlement = $tenant->entitlement;

        if ($tenant->status !== 'active') {
            return 'suspended';
        }

        if ($entitlement && $entitlement->billing_status === 'active'
            && $entitlement->paid_through_at && $entitlement->paid_through_at->isFuture()) {
            return 'paid';
        }

        if ($entitlement && $entitlement->billing_status === 'past_due'
            && $entitlement->grace_ends_at && $entitlement->grace_ends_at->isFuture()) {
            return 'grace';
        }

        if ($entitlement && $entitlement->billing_status === 'canceled'
            && $entitlement->paid_through_at && $entitlement->paid_through_at->isFuture()) {
            return 'paid_through';
        }

        if ($entitlement && $entitlement->billing_status === 'none'
            && $entitlement->trial_ends_at && $entitlement->trial_ends_at->isFuture()) {
            return 'evaluation';
        }

        return 'inactive';
    }

    public function canUse(Tenant $tenant, ApiKey $key)
    {
        if ($key->status !== 'active' || $key->revoked_at
            || ($key->expires_at && ! $key->expires_at->isFuture())) {
            return false;
        }

        $state = $this->state($tenant);

        if ($key->kind === 'legacy') {
            return $state !== 'suspended';
        }

        if ($key->kind === 'temporary') {
            return $state === 'evaluation' || $state === 'paid';
        }

        return $key->kind === 'paid' && in_array($state, ['paid', 'grace', 'paid_through'], true);
    }

    public function canCreate(Tenant $tenant, $kind)
    {
        $state = $this->state($tenant);

        return ($kind === 'temporary' && $state === 'evaluation')
            || ($kind === 'paid' && $state === 'paid');
    }

    public function payload(Tenant $tenant)
    {
        $entitlement = $tenant->entitlement;

        return [
            'state' => $this->state($tenant),
            'trial_ends_at' => $entitlement ? $entitlement->trial_ends_at : null,
            'billing_status' => $entitlement ? $entitlement->billing_status : 'none',
            'paid_through_at' => $entitlement ? $entitlement->paid_through_at : null,
            'grace_ends_at' => $entitlement ? $entitlement->grace_ends_at : null,
            'can_create_temporary_key' => $this->canCreate($tenant, 'temporary'),
            'can_create_paid_key' => $this->canCreate($tenant, 'paid'),
        ];
    }
}
