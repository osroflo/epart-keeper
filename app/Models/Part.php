<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $fillable = ['part_number', 'manufacturer', 'description', 'stock', 'price', 'is_complete', 'missing_attributes', 'origin'];

    public function source()
    {
        return $this->hasOne(MetadataOrigin::class,'id','origin');
    }
}
