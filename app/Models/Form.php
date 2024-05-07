<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use HasFactory;
    public $timestamps = false;
    public function questions() {
        return $this->hasMany(Question::class);
    }
    public function allowed_domain() {
        return $this->hasMany(AllowedDomain::class);
    }
    public function response() {
        return $this->hasMany(Response::class);
    }
}
