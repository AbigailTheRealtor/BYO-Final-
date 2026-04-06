<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerCounterBidding extends Model
{
    use HasFactory;

    protected $appends = ['get'];

    protected $table = 'buyer_counter_bidding';

    protected $fillable = [
        'user_id',
        'buyer_agent_auction_id',
        'buyer_agent_auction_bid_id',
        'property_type',
        'parent_counter_id',
    ];

    /**
     * Relationship with user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with auction
     */
    public function auction()
    {
        return $this->belongsTo(BuyerAgentAuction::class, 'buyer_agent_auction_id');
    }

    /**
     * Relationship with bid
     */
    public function bid()
    {
        return $this->belongsTo(BuyerAgentAuctionBid::class, 'buyer_agent_auction_bid_id');
    }

    /**
     * Relationship with meta data
     */
    public function meta()
    {
        return $this->hasMany(BuyerCounterBiddingMeta::class, 'counter_bidding_id');
    }

    /**
     * Save meta data
     */
    public function saveMeta($key, $value)
    {
        return $this->meta()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }

    /**
     * Get meta value
     */
    public function getMeta($key, $default = null)
    {
        $meta = $this->meta()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Get all meta as array
     */
    public function getAllMeta()
    {
        return $this->meta->pluck('meta_value', 'meta_key')->toArray();
    }

    public function getGetAttribute()
    {
        $data = [];
        $metas = BuyerCounterBiddingMeta::where('counter_bidding_id', $this->id)->get();
        foreach ($metas as $row) {
            if (gettype(json_decode($row->meta_value)) == 'array') {
                $value = json_decode($row->meta_value);
            } else {
                $value = $row->meta_value;
            }
            $data[$row->meta_key] = $value;
        }
        $collection = new Collection();
        $collection->push((object) $data);
        return $collection->first();
    }
}
