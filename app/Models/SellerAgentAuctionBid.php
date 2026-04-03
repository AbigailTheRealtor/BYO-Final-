<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerAgentAuctionBid extends Model
{
    use HasFactory;
    protected $appends = ["get"];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(SellerAgentAuction::class, 'seller_agent_auction_id', 'id')->withDefault();
    }

    public function meta()
    {
        return $this->hasMany(SellerAgentAuctionBidMeta::class);
    }

    public function counterTerms()
    {
        return $this->hasMany(SellerCounterTerm::class, 'seller_agent_auction_bid_id');
    }

    public function acceptedBidSummary()
    {
        return $this->hasOne(AcceptedBidSummary::class, 'accepted_bid_id');
    }

    public function getBidStatusAttribute(): string
    {
        // Terminal states take priority
        if ($this->accepted === 'rejected') {
            return 'Rejected';
        }

        if ($this->accepted === 'accepted' || $this->acceptedBidSummary()->exists()) {
            return 'Accepted';
        }

        // Non-terminal: countered if an *active* (status=1) SellerCounterTerm exists
        // Rejected counter terms (status=0) leave the bid negotiable/active.
        if ($this->counterTerms()->where('status', 1)->exists()) {
            return 'Countered';
        }

        return 'Active';
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

    public function getGetAttribute()
    {
        $data = [];
        $metas = SellerAgentAuctionBidMeta::where('seller_agent_auction_bid_id', $this->id)->get();
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
