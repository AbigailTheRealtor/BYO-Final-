<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantCounterBidding extends Model
{
    use HasFactory;

    protected $table = 'tenant_counter_bidding';

    protected $fillable = [
        'user_id',
        'tenant_agent_auction_id',
        'tenant_agent_auction_bid_id',
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
        return $this->belongsTo(TenantAgentAuction::class, 'tenant_agent_auction_id');
    }

    /**
     * Relationship with bid
     */
    public function bid()
    {
        return $this->belongsTo(TenantAgentAuctionBid::class, 'tenant_agent_auction_bid_id');
    }

    /**
     * Relationship with meta data
     */
    public function meta()
    {
        return $this->hasMany(TenantCounterBiddingMeta::class, 'counter_bidding_id');
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
}
