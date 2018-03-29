<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Octopart extends Model
{
    protected $table = "octoparts";

    protected $fillable = ['part_number', 'manufacturer', 'description', 'stock', 'price', 'is_complete', 'missing_attributes', 'origin'];

    public function source()
    {
        return $this->hasOne(MetadataOrigin::class,'id','origin');
    }
}
