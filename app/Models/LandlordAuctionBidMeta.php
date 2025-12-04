<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordAuctionBidMeta extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'landlord_auction_bid_meta';
    protected $fillable = ['landlord_auction_bid_id', 'meta_key', 'meta_value'];

    public function bid()
    {
        return $this->belongsTo(LandlordAuctionBid::class);
    }
}
