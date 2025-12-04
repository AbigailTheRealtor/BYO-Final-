<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerAgentAuctionBidMeta extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['buyer_agent_auction_bid_id', 'meta_key', 'meta_value'];
}
