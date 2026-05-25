<?php

namespace App;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasUuids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the API key.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Build a deterministic hash for a raw API key.
     *
     * @param  string  $key
     * @return string
     */
    public static function hashKey($key)
    {
        return hash('sha256', $key);
    }

    /**
     * Return the display prefix for a raw API key.
     *
     * @param  string  $key
     * @return string
     */
    public static function keyPrefix($key)
    {
        return substr($key, 0, (int) config('milog.api_keys.prefix_length', 12));
    }
}
