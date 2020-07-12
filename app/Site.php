<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    public function pages()
    {
        return $this->belongsToMany(Page::class);
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
