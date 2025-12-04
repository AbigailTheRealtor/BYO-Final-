<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterViewType extends Model
{
    use HasFactory;

    /**
     * Get all of the comments for the WaterViewType
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function property_auction()
    {
        return $this->hasMany(PropertyAuctionWaterViewType::class);
    }
}
