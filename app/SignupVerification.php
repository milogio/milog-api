<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SignupVerification extends Model
{
    protected $fillable = ['user_id', 'tenant_id', 'token_hash', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
