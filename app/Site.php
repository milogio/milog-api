<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'published_at',
    ];

    protected $hidden = ['pivot'];

    public function pages()
    {
        return $this->belongsToMany(Page::class);
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
