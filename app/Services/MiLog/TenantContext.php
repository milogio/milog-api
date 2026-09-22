<?php

namespace App\Services\MiLog;

use App\Tenant;
use App\User;

class TenantContext
{
    public $tenant;

    public $user;

    public $role;

    public function __construct(Tenant $tenant, User $user, $role)
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->role = $role;
    }
}
