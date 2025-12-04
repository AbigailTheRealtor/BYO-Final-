<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyType extends Model
{
    use HasFactory;

    public function property_auction()
    {
        return $this->hasMany(PropertyAuctionPropertyType::class);
    }
}
