<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerCriteriaAuctionBidMeta extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['buyer_criteria_auction_bid_id', 'meta_key', 'meta_value'];

    public function bid()
    {
        return $this->belongsTo(BuyerCriteriaAuctionBid::class);
    }
}
