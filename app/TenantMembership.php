<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantMembership extends Pivot
{
    protected $table = 'tenant_user';

    protected $fillable = ['tenant_id', 'user_id', 'role', 'status'];
}
