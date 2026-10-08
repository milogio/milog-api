<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TenantEntitlement extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'tenant_id';

    protected $keyType = 'string';

    protected $fillable = ['tenant_id', 'trial_ends_at', 'billing_status', 'paid_through_at', 'grace_ends_at'];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'paid_through_at' => 'datetime',
        'grace_ends_at' => 'datetime',
    ];
}
