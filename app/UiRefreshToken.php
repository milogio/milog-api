<?php

namespace App;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UiRefreshToken extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id', 'user_id', 'tenant_id', 'access_token_id', 'auth_version', 'token_hash',
        'expires_at', 'used_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'auth_version' => 'integer',
    ];

    public static function hashToken($token)
    {
        return hash('sha256', $token);
    }
}
