<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordCounterBiddingMeta extends Model
{
    use HasFactory;

    protected $table = 'landlord_counter_bidding_meta';

    protected $fillable = [
        'counter_bidding_id',
        'meta_key',
        'meta_value',
    ];

    /**
     * Relationship with main counter bidding
     */
    public function counterBidding()
    {
        return $this->belongsTo(LandlordCounterBidding::class, 'counter_bidding_id');
    }
}
