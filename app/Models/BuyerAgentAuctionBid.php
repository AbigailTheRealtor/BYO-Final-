<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerAgentAuctionBid extends Model
{
    use HasFactory;
    protected $appends = ["get"];
    protected $with = ['meta'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(BuyerAgentAuction::class, 'buyer_agent_auction_id', 'id')->withDefault();
    }

    public function meta()
    {
        return $this->hasMany(BuyerAgentAuctionBidMeta::class);
    }

    public function counterTerms()
    {
        return $this->hasMany(BuyerCounterBidding::class, 'buyer_agent_auction_bid_id');
    }

    public function counterBids()
    {
        return $this->hasMany(BuyerCounterBidding::class, 'buyer_agent_auction_bid_id');
    }

    public function acceptedBidSummary()
    {
        return $this->hasOne(AcceptedBidSummary::class, 'accepted_bid_id');
    }

    public function getBidStatusAttribute(): string
    {
        if ($this->accepted === 'accepted') {
            return 'Accepted';
        }

        if ($this->accepted === 'rejected') {
            return 'Rejected';
        }

        $latestCounter = $this->relationLoaded('counterTerms')
            ? $this->counterTerms->sortByDesc('created_at')->first()
            : $this->counterTerms()->latest()->first();
        if ($latestCounter) {
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
        $metas = $this->meta;
        foreach ($metas as $row) {
            $decoded = json_decode($row->meta_value, true);
            if (is_array($decoded)) {
                $value = $decoded;
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
