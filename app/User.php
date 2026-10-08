<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $attributes = [
        'status' => 'active',
        'auth_version' => 1,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'status', 'auth_version', 'terms_accepted_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'auth_version' => 'integer',
        'terms_accepted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::updating(function ($user) {
            if ($user->isDirty('password') && ! $user->isDirty('auth_version')) {
                $user->auth_version = ((int) $user->getOriginal('auth_version')) + 1;
            }
        });
    }

    public function sites() {
        return $this->belongsToMany(Site::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class)
            ->using(TenantMembership::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function revokeUiSessions()
    {
        $this->forceFill(['auth_version' => $this->auth_version + 1])->save();
    }
}
