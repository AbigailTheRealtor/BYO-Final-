<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordAuction extends Model
{
    use HasFactory;
    protected $appends = ["get"];

    public function meta()
    {
        return $this->hasMany(LandlordAuctionMeta::class);
    }

    public function bot_questions()
    {
        return $this->morphMany(BotQuestion::class, 'auction');
    }

    public function unanswered_bot_questions()
    {
        return $this->morphMany(UnansweredBotQuestion::class, 'auction');
    }

    public function chat_tokens()
    {
        return $this->morphMany(AuctionChatToken::class, 'auction');
    }

    public function saveMeta($key, $val)
    {
        return $this->meta()->updateOrCreate(["meta_key" => $key], ["meta_value" => $val]);
    }

    public function info($key)
    {
        $data = $this->meta->where('meta_key', $key);
        if ($data->count() > 0) {
            return $data->first()->meta_value;
        } else {
            return false;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bids()
    {
        return $this->hasMany(LandlordAuctionBid::class)->selectRaw("*,( SELECT landlord_auction_bid_meta.meta_value FROM landlord_auction_bid_meta WHERE landlord_auction_bid_meta.landlord_auction_bid_id=landlord_auction_bids.id AND landlord_auction_bid_meta.meta_key = 'offer_rental_price' ) as price ");
    }

    /* public function max_bid()
    {
        return $this->hasMany(LandlordAuctionBid::class)->selectRaw("*,( SELECT landlord_auction_bid_meta.meta_value FROM landlord_auction_bid_meta WHERE landlord_auction_bid_meta.landlord_auction_bid_id=landlord_auction_bids.id AND landlord_auction_bid_meta.meta_key = 'offer_rental_price' ) as price ");
    } */

    public function getGetAttribute()
    {
        $data = [];
        $metas = LandlordAuctionMeta::where('landlord_auction_id', $this->id)->get();
        foreach ($metas as $row) {
            $data[$row->meta_key] = $row->meta_value;
        }
        $collection = new Collection();
        $collection->push($data);
        return (object) $collection->first();
    }
}
