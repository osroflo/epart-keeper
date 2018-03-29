<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    protected $fillable = ['label'];
    protected $hidden = ['id', 'created_at', 'updated_at'];
}
