<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    public function sites() {
        return $this->belongsToMany(Site::class);
    }

    public function components() {
        return $this->belongsToMany(Component::class);
    }
}
